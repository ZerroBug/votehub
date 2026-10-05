document.addEventListener('DOMContentLoaded', function(){
    const btn=document.getElementById('mobileMenu');
    const sidebar=document.getElementById('sidebar');
    if(btn) btn.addEventListener('click',()=>sidebar.classList.toggle('open'));
});
