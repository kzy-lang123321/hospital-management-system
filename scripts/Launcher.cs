using System;
using System.Diagnostics;
using System.IO;
using System.Net.Sockets;
using System.Text;
using System.Threading;
using System.Windows.Forms;
using System.Drawing;

namespace HospitalManagementSystem
{
    static class Program
    {
        private static Process phpProcess = null;
        private static NotifyIcon trayIcon = null;
        private static string appDir = "";
        private const int AppPort = 8765;

        [STAThread]
        static void Main()
        {
            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);

            appDir = AppDomain.CurrentDomain.BaseDirectory.TrimEnd('\\');

            // 1. Locate PHP
            string phpBin = FindPhpExecutable();
            if (string.IsNullOrEmpty(phpBin))
            {
                MessageBox.Show(
                    "PHP executable was not found.\n\nPlease ensure XAMPP is installed at C:\\xampp or PHP is added to system PATH.",
                    "Hospital Management System - Error",
                    MessageBoxButtons.OK,
                    MessageBoxIcon.Error
                );
                return;
            }

            // 2. Ensure MySQL is running
            EnsureMysqlRunning();

            // 3. Terminate any previous instance using the port
            KillProcessOnPort(AppPort);

            // 4. Start PHP Built-in Server in background
            StartPhpServer(phpBin, AppPort);

            // Wait a moment for server to bind
            Thread.Sleep(1200);

            // 5. Setup System Tray Icon
            SetupTrayIcon();

            // 6. Launch Dedicated App Window
            LaunchAppWindow(AppPort);

            // Run message loop for system tray
            Application.Run();
        }

        private static string FindPhpExecutable()
        {
            string[] candidates = new string[]
            {
                @"C:\xampp\php\php.exe",
                @"C:\wamp64\bin\php\php8.2.0\php.exe",
                @"C:\tools\php\php.exe",
                @"C:\php\php.exe"
            };

            foreach (var p in candidates)
            {
                if (File.Exists(p)) return p;
            }

            // Check PATH
            var envPath = Environment.GetEnvironmentVariable("PATH");
            if (envPath != null)
            {
                foreach (var dir in envPath.Split(';'))
                {
                    string candidate = Path.Combine(dir.Trim(), "php.exe");
                    if (File.Exists(candidate)) return candidate;
                }
            }

            return null;
        }

        private static bool IsPortOpen(int port)
        {
            try
            {
                using (var client = new TcpClient())
                {
                    var result = client.BeginConnect("127.0.0.1", port, null, null);
                    bool success = result.AsyncWaitHandle.WaitOne(1000);
                    if (success && client.Connected)
                    {
                        client.EndConnect(result);
                        return true;
                    }
                }
            }
            catch {}
            return false;
        }

        private static void EnsureMysqlRunning()
        {
            if (IsPortOpen(3306)) return;

            string mysqld = @"C:\xampp\mysql\bin\mysqld.exe";
            if (File.Exists(mysqld))
            {
                try
                {
                    var psi = new ProcessStartInfo
                    {
                        FileName = mysqld,
                        Arguments = @"--defaults-file=""C:\xampp\mysql\bin\my.ini"" --standalone",
                        CreateNoWindow = true,
                        UseShellExecute = false,
                        WindowStyle = ProcessWindowStyle.Hidden
                    };
                    Process.Start(psi);
                    Thread.Sleep(2000);
                }
                catch {}
            }
        }

        private static void KillProcessOnPort(int port)
        {
            try
            {
                var psi = new ProcessStartInfo("cmd.exe", "/c \"for /f \"tokens=5\" %a in ('netstat -aon ^| findstr \":" + port + " \"') do taskkill /f /pid %a\"")
                {
                    CreateNoWindow = true,
                    UseShellExecute = false
                };
                var p = Process.Start(psi);
                p.WaitForExit(2000);
            }
            catch {}
        }

        private static void StartPhpServer(string phpBin, int port)
        {
            try
            {
                var psi = new ProcessStartInfo
                {
                    FileName = phpBin,
                    Arguments = string.Format("-S 127.0.0.1:{0} -t \"{1}\"", port, appDir),
                    WorkingDirectory = appDir,
                    CreateNoWindow = true,
                    UseShellExecute = false,
                    WindowStyle = ProcessWindowStyle.Hidden
                };
                phpProcess = Process.Start(psi);
            }
            catch (Exception ex)
            {
                MessageBox.Show("Failed to start PHP server:\n" + ex.Message, "HMS Server Error", MessageBoxButtons.OK, MessageBoxIcon.Error);
            }
        }

        private static void LaunchAppWindow(int port)
        {
            string url = "http://127.0.0.1:" + port + "/";
            string browser = null;

            string[] browserCandidates = new string[]
            {
                Environment.GetFolderPath(Environment.SpecialFolder.ProgramFilesX86) + @"\Microsoft\Edge\Application\msedge.exe",
                Environment.GetFolderPath(Environment.SpecialFolder.ProgramFiles) + @"\Microsoft\Edge\Application\msedge.exe",
                Environment.GetFolderPath(Environment.SpecialFolder.ProgramFiles) + @"\Google\Chrome\Application\chrome.exe",
                Environment.GetFolderPath(Environment.SpecialFolder.ProgramFilesX86) + @"\Google\Chrome\Application\chrome.exe"
            };

            foreach (var b in browserCandidates)
            {
                if (File.Exists(b))
                {
                    browser = b;
                    break;
                }
            }

            if (!string.IsNullOrEmpty(browser))
            {
                var psi = new ProcessStartInfo
                {
                    FileName = browser,
                    Arguments = string.Format("--app=\"{0}\" --window-size=1366,860 --app-id=hms-hospital-app", url),
                    UseShellExecute = false
                };
                var browserProc = Process.Start(psi);
                if (browserProc != null)
                {
                    // Watch for browser exit in a background thread
                    var t = new Thread(() =>
                    {
                        try
                        {
                            browserProc.WaitForExit();
                            // Shutdown after browser closes
                            Application.Exit();
                        }
                        catch {}
                    });
                    t.IsBackground = true;
                    t.Start();
                }
            }
            else
            {
                Process.Start(url);
            }
        }

        private static void SetupTrayIcon()
        {
            trayIcon = new NotifyIcon();
            trayIcon.Text = "Hospital Management System (Active)";
            trayIcon.Icon = SystemIcons.Application;
            trayIcon.Visible = true;

            var menu = new ContextMenuStrip();
            menu.Items.Add("🏥 Open HMS Dashboard", null, (s, e) => LaunchAppWindow(AppPort));
            menu.Items.Add("➕ Register Patient", null, (s, e) => {
                Process.Start("http://127.0.0.1:" + AppPort + "/?page=patients_create");
            });
            menu.Items.Add("-");
            menu.Items.Add("❌ Exit Application", null, (s, e) => {
                Shutdown();
                Application.Exit();
            });

            trayIcon.ContextMenuStrip = menu;
            trayIcon.DoubleClick += (s, e) => LaunchAppWindow(AppPort);

            Application.ApplicationExit += (s, e) => Shutdown();
        }

        private static void Shutdown()
        {
            if (trayIcon != null)
            {
                trayIcon.Visible = false;
                trayIcon.Dispose();
                trayIcon = null;
            }

            if (phpProcess != null && !phpProcess.HasExited)
            {
                try
                {
                    phpProcess.Kill();
                }
                catch {}
                phpProcess = null;
            }

            KillProcessOnPort(AppPort);
        }
    }
}
