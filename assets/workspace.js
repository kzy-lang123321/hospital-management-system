
(() => {
 'use strict';
 const sidebar = document.getElementById('mainSidebar');
 const content = document.querySelector('body > div.flex-1 > div.flex-1');
 if (content) { content.id = 'workspaceContent'; content.tabIndex = -1; content.setAttribute('role','main'); }
 const currentPage = new URL(location.href).searchParams.get('page') || 'dashboard';
 const links = [...(sidebar?.querySelectorAll('nav a[href]') || [])];
 links.forEach(link => { if (new URL(link.href).searchParams.get('page') === currentPage) link.setAttribute('aria-current','page'); });
 const dialog = document.getElementById('workspaceDialog');
 const input = document.getElementById('workspaceQuery');
 const results = document.getElementById('workspaceResults');
 let opener;
 function render() {
   const term = input.value.trim().toLowerCase();
   const matches = links.filter(link => link.textContent.toLowerCase().includes(term));
   results.replaceChildren();
   matches.forEach(link => {
     const item = document.createElement('a');
     item.href = link.href;
     item.textContent = link.textContent.trim();
     if (link.getAttribute('aria-current')) { const tag = document.createElement('small'); tag.textContent = 'Current'; item.append(tag); }
     results.append(item);
   });
   if (!matches.length) { const empty = document.createElement('p'); empty.className='workspace-caption'; empty.textContent='No matching workspace. Try patients, pharmacy, or reports.'; results.append(empty); }
   document.getElementById('workspaceCount').textContent = `${matches.length} workspaces available to your role`;
 }
 function open() { opener=document.activeElement; input.value=''; render(); dialog.showModal(); input.focus(); }
 document.getElementById('workspaceOpen')?.addEventListener('click',open);
 document.getElementById('workspaceClose')?.addEventListener('click',() => dialog.close());
 dialog?.addEventListener('close',() => opener?.focus());
 dialog?.addEventListener('click',event => { if(event.target === dialog) { const rect=dialog.getBoundingClientRect(); if(event.clientX<rect.left || event.clientX>rect.right || event.clientY<rect.top || event.clientY>rect.bottom) dialog.close(); } });
 input?.addEventListener('input',render);
 input?.addEventListener('keydown',event => { if(event.key==='ArrowDown') { event.preventDefault(); results.querySelector('a')?.focus(); } if(event.key==='Enter') { event.preventDefault(); results.querySelector('a')?.click(); } });
 results?.addEventListener('keydown',event => { if(!['ArrowDown','ArrowUp'].includes(event.key)) return; event.preventDefault(); const rows=[...results.querySelectorAll('a')]; const next=rows.indexOf(document.activeElement)+(event.key==='ArrowDown'?1:-1); if(next<0) input.focus(); else rows[Math.min(next,rows.length-1)]?.focus(); });
 document.addEventListener('keydown',event => { if((event.ctrlKey||event.metaKey)&&event.key.toLowerCase()==='k') { event.preventDefault(); if(dialog.open) dialog.close(); else open(); } });
 const readable = document.getElementById('workspaceReadable');
 const setReadable = value => { document.body.classList.toggle('workspace-readable',value); readable?.setAttribute('aria-pressed',String(value)); };
 try { setReadable(localStorage.getItem('hms.readable')==='1'); } catch (_) { /* Storage is optional. */ }
 readable?.addEventListener('click',() => { const value=!document.body.classList.contains('workspace-readable'); setReadable(value); try { localStorage.setItem('hms.readable',value?'1':'0'); } catch (_) {} });
 const toast = document.getElementById('flashToast');
 if(toast) { toast.setAttribute('role','status'); toast.setAttribute('aria-live','polite'); toast.querySelector('button')?.setAttribute('aria-label','Dismiss notification'); }
 const menu=document.getElementById('mobileMenuBtn');
 if(menu && sidebar) { menu.setAttribute('aria-controls','mainSidebar'); const sync=() => menu.setAttribute('aria-expanded',String(!sidebar.classList.contains('-translate-x-full'))); sync(); new MutationObserver(sync).observe(sidebar,{attributes:true,attributeFilter:['class']}); }
 // Keep medical and patient information out of browser storage.
})();
