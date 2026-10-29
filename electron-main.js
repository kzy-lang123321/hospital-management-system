const { app, BrowserWindow, Menu, dialog, shell, Tray } = require('electron');
const path = require('path');
const { spawn, execSync } = require('child_process');
const http = require('http');
const net = require('net');
const fs = require('fs');

let mainWindow = null;
let splashWindow = null;
let phpProcess = null;
let mysqlProcess = null;
let serverPort = 8765;

const appRoot = __dirname;

// Helper: Locate PHP binary
function findPhpPath() {
    if (process.env.PHP_PATH && fs.existsSync(process.env.PHP_PATH)) {
        return process.env.PHP_PATH;
    }
    const candidatePaths = [
        'C:\\xampp\\php\\php.exe',
        'C:\\wamp64\\bin\\php\\php8.2.0\\php.exe',
        'C:\\tools\\php\\php.exe',
        'C:\\php\\php.exe'
    ];
    for (const p of candidatePaths) {
        if (fs.existsSync(p)) {
            return p;
        }
    }
    // Try system PATH
    try {
        const out = execSync('where php', { encoding: 'utf-8' }).trim();
        if (out) {
            const first = out.split('\n')[0].trim();
            if (fs.existsSync(first)) return first;
        }
    } catch (e) {
        // Not in PATH
    }
    return null;
}

// Helper: Check if a TCP port is open
function checkPortOpen(port, host = '127.0.0.1') {
    return new Promise((resolve) => {
        const socket = new net.Socket();
        socket.setTimeout(1500);
        socket.on('connect', () => {
            socket.destroy();
            resolve(true);
        });
        socket.on('timeout', () => {
            socket.destroy();
            resolve(false);
        });
        socket.on('error', () => {
            resolve(false);
        });
        socket.connect(port, host);
    });
}

// Helper: Find a free TCP port
function getFreePort(startingPort = 8765) {
    return new Promise((resolve, reject) => {
        const server = net.createServer();
        server.unref();
        server.on('error', () => {
            resolve(getFreePort(startingPort + 1));
        });
        server.listen(startingPort, '127.0.0.1', () => {
            const port = server.address().port;
            server.close(() => resolve(port));
        });
    });
}

// Helper: Try auto-starting MySQL if XAMPP is present and MySQL isn't running
async function ensureMysqlRunning() {
    const isRunning = await checkPortOpen(3306);
    if (isRunning) {
        console.log('[HMS Desktop] MySQL is already active on port 3306.');
        return true;
    }

    const xamppMysql = 'C:\\xampp\\mysql\\bin\\mysqld.exe';
    if (fs.existsSync(xamppMysql)) {
        console.log('[HMS Desktop] Starting XAMPP MySQL daemon...');
        try {
            mysqlProcess = spawn(xamppMysql, ['--defaults-file=C:\\xampp\\mysql\\bin\\my.ini', '--standalone'], {
                detached: true,
                stdio: 'ignore'
            });
            mysqlProcess.unref();
            // Wait up to 5 seconds for port 3306
            for (let i = 0; i < 10; i++) {
                await new Promise((r) => setTimeout(r, 500));
                if (await checkPortOpen(3306)) {
                    console.log('[HMS Desktop] MySQL started successfully.');
                    return true;
                }
            }
        } catch (err) {
            console.error('[HMS Desktop] Failed to auto-launch MySQL:', err);
        }
    }
    return false;
}

// Start PHP built-in server
function startPhpServer(phpPath, port) {
    return new Promise((resolve, reject) => {
        console.log(`[HMS Desktop] Starting PHP Built-in Server on 127.0.0.1:${port}...`);
        
        phpProcess = spawn(phpPath, [
            '-S', `127.0.0.1:${port}`,
            '-t', appRoot
        ], {
            cwd: appRoot,
            env: { ...process.env }
        });

        phpProcess.stdout.on('data', (data) => {
            console.log(`[PHP Server] ${data}`);
        });

        phpProcess.stderr.on('data', (data) => {
            console.log(`[PHP Server] ${data}`);
        });

        phpProcess.on('error', (err) => {
            console.error('[PHP Server Error]', err);
            reject(err);
        });

        // Poll until HTTP server responds
        let attempts = 0;
        const maxAttempts = 30;
        const checkInterval = setInterval(() => {
            attempts++;
            const req = http.get(`http://127.0.0.1:${port}/`, (res) => {
                clearInterval(checkInterval);
                resolve();
            });
            req.on('error', () => {
                if (attempts >= maxAttempts) {
                    clearInterval(checkInterval);
                    reject(new Error('PHP Server timed out while starting.'));
                }
            });
            req.end();
        }, 300);
    });
}

// Create Splash Screen
function createSplashWindow() {
    splashWindow = new BrowserWindow({
        width: 500,
        height: 320,
        transparent: false,
        frame: false,
        alwaysOnTop: true,
        resizable: false,
        center: true,
        webPreferences: {
            nodeIntegration: false,
            contextIsolation: true
        }
    });

    const splashHtml = `
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset="utf-8">
      <style>
        body {
          margin: 0;
          padding: 0;
          font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
          background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
          color: #f8fafc;
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          height: 100vh;
          user-select: none;
          box-sizing: border-box;
          border: 1px solid #334155;
        }
        .icon-box {
          width: 72px;
          height: 72px;
          border-radius: 16px;
          background: #0d9488;
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 38px;
          box-shadow: 0 10px 25px rgba(13, 148, 136, 0.4);
          margin-bottom: 20px;
        }
        h1 {
          font-size: 22px;
          font-weight: 700;
          margin: 0 0 6px 0;
          letter-spacing: -0.5px;
        }
        p {
          font-size: 13px;
          color: #94a3b8;
          margin: 0 0 24px 0;
        }
        .spinner {
          width: 24px;
          height: 24px;
          border: 3px solid rgba(255,255,255,0.15);
          border-top-color: #14b8a6;
          border-radius: 50%;
          animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
          to { transform: rotate(360deg); }
        }
        .status {
          font-size: 12px;
          color: #cbd5e1;
          margin-top: 14px;
        }
      </style>
    </head>
    <body>
      <div class="icon-box">🏥</div>
      <h1>Hospital Management System</h1>
      <p>Starting Desktop Services & Clinical Engine...</p>
      <div class="spinner"></div>
      <div class="status" id="status-text">Initializing local environment...</div>
    </body>
    </html>
    `;

    splashWindow.loadURL('data:text/html;charset=utf-8,' + encodeURIComponent(splashHtml));
}

// Create Main Application Window
function createMainWindow(port) {
    mainWindow = new BrowserWindow({
        width: 1366,
        height: 860,
        minWidth: 1024,
        minHeight: 700,
        show: false,
        title: 'Hospital Management System (HMS) — Desktop Edition',
        backgroundColor: '#0f172a',
        webPreferences: {
            nodeIntegration: false,
            contextIsolation: true,
            sandbox: true,
            spellcheck: true
        }
    });

    // Native Application Menu
    const template = [
        {
            label: 'File',
            submenu: [
                {
                    label: 'Dashboard',
                    accelerator: 'CmdOrCtrl+D',
                    click: () => mainWindow.loadURL(`http://127.0.0.1:${port}/?page=dashboard`)
                },
                {
                    label: 'Register New Patient',
                    accelerator: 'CmdOrCtrl+N',
                    click: () => mainWindow.loadURL(`http://127.0.0.1:${port}/?page=patients_create`)
                },
                { type: 'separator' },
                {
                    label: 'Print Document / Receipt',
                    accelerator: 'CmdOrCtrl+P',
                    click: () => mainWindow.webContents.print()
                },
                { type: 'separator' },
                {
                    label: 'Exit',
                    accelerator: 'CmdOrCtrl+Q',
                    click: () => app.quit()
                }
            ]
        },
        {
            label: 'View',
            submenu: [
                { role: 'reload', accelerator: 'CmdOrCtrl+R' },
                { role: 'forceReload' },
                { type: 'separator' },
                { role: 'resetZoom' },
                { role: 'zoomIn' },
                { role: 'zoomOut' },
                { type: 'separator' },
                { role: 'togglefullscreen', accelerator: 'F11' }
            ]
        },
        {
            label: 'Modules',
            submenu: [
                { label: 'Outpatient (OPD)', click: () => mainWindow.loadURL(`http://127.0.0.1:${port}/?page=opd_queue`) },
                { label: 'Inpatient (IPD Bed Rack)', click: () => mainWindow.loadURL(`http://127.0.0.1:${port}/?page=ipd_beds`) },
                { label: 'Pharmacy & Dispensing', click: () => mainWindow.loadURL(`http://127.0.0.1:${port}/?page=pharmacy_queue`) },
                { label: 'Laboratory / Diagnostics', click: () => mainWindow.loadURL(`http://127.0.0.1:${port}/?page=lab_worklist`) },
                { label: 'Billing & Cashier Desk', click: () => mainWindow.loadURL(`http://127.0.0.1:${port}/?page=billing_invoices`) },
                { label: 'Master Patient Index', click: () => mainWindow.loadURL(`http://127.0.0.1:${port}/?page=patients_index`) }
            ]
        },
        {
            label: 'Help',
            submenu: [
                {
                    label: 'About HMS Desktop',
                    click: () => {
                        dialog.showMessageBox(mainWindow, {
                            type: 'info',
                            title: 'About Hospital Management System',
                            message: 'Hospital Management System (HMS)',
                            detail: 'Desktop Edition v1.0.0\nEnterprise clinical, administrative, and cashier workflow system.\nBuilt with Electron, PHP & MariaDB.'
                        });
                    }
                },
                {
                    label: 'Developer Tools',
                    accelerator: 'CmdOrCtrl+Shift+I',
                    click: () => mainWindow.webContents.toggleDevTools()
                }
            ]
        }
    ];

    const menu = Menu.buildFromTemplate(template);
    Menu.setApplicationMenu(menu);

    mainWindow.loadURL(`http://127.0.0.1:${port}/`);

    mainWindow.once('ready-to-show', () => {
        if (splashWindow && !splashWindow.isDestroyed()) {
            splashWindow.close();
            splashWindow = null;
        }
        mainWindow.maximize();
        mainWindow.show();
    });

    mainWindow.on('closed', () => {
        mainWindow = null;
    });
}

// Clean shutdown: Terminate child processes
function cleanup() {
    if (phpProcess) {
        console.log('[HMS Desktop] Terminating PHP server...');
        try {
            if (process.platform === 'win32') {
                execSync(`taskkill /pid ${phpProcess.pid} /T /F`);
            } else {
                phpProcess.kill('SIGTERM');
            }
        } catch (e) {
            try { phpProcess.kill(); } catch (err) {}
        }
        phpProcess = null;
    }
}

// Application Lifecycle
app.whenReady().then(async () => {
    createSplashWindow();

    // 1. Locate PHP
    const phpPath = findPhpPath();
    if (!phpPath) {
        if (splashWindow && !splashWindow.isDestroyed()) splashWindow.close();
        dialog.showErrorBox(
            'PHP Runtime Not Found',
            'Could not find php.exe.\n\nPlease install XAMPP at C:\\xampp or ensure PHP is in your system PATH, or set the PHP_PATH environment variable.'
        );
        app.quit();
        return;
    }

    // 2. Verify or Auto-Start MySQL
    await ensureMysqlRunning();

    // 3. Obtain a free TCP port
    try {
        serverPort = await getFreePort(8765);
    } catch (e) {
        serverPort = 8765;
    }

    // 4. Launch PHP Server
    try {
        await startPhpServer(phpPath, serverPort);
        createMainWindow(serverPort);
    } catch (err) {
        if (splashWindow && !splashWindow.isDestroyed()) splashWindow.close();
        dialog.showErrorBox(
            'Server Startup Error',
            `Failed to start PHP application server:\n${err.message}`
        );
        app.quit();
    }
});

app.on('will-quit', cleanup);
app.on('before-quit', cleanup);

app.on('window-all-closed', () => {
    cleanup();
    if (process.platform !== 'darwin') {
        app.quit();
    }
});
