document.addEventListener('DOMContentLoaded', function(){
    const btn=document.getElementById('mobileMenu');
    const sidebar=document.getElementById('sidebar');
    if(!btn || !sidebar) return;

    let backdrop=document.getElementById('mobileNavBackdrop');
    if(!backdrop){
        backdrop=document.createElement('div');
        backdrop.id='mobileNavBackdrop';
        backdrop.className='mobile-nav-backdrop';
        document.body.appendChild(backdrop);
    }

    const closeMenu=()=>{
        sidebar.classList.remove('open');
        backdrop.classList.remove('show');
        document.body.classList.remove('mobile-menu-open');
        btn.setAttribute('aria-expanded','false');
    };
    const openMenu=()=>{
        sidebar.classList.add('open');
        backdrop.classList.add('show');
        document.body.classList.add('mobile-menu-open');
        btn.setAttribute('aria-expanded','true');
    };

    btn.setAttribute('aria-expanded','false');
    btn.addEventListener('click',()=>sidebar.classList.contains('open') ? closeMenu() : openMenu());
    backdrop.addEventListener('click',closeMenu);
    sidebar.querySelectorAll('a').forEach(a=>a.addEventListener('click',closeMenu));
    document.addEventListener('keydown',e=>{ if(e.key==='Escape') closeMenu(); });
    window.addEventListener('resize',()=>{ if(window.innerWidth>991) closeMenu(); });
});
