(()=>{'use strict';
 const menu=document.querySelector('.menu-toggle');const mobile=document.querySelector('#mobile-nav');
 if(menu&&mobile){menu.addEventListener('click',()=>{const expanded=menu.getAttribute('aria-expanded')==='true';menu.setAttribute('aria-expanded',String(!expanded));mobile.hidden=expanded;});}
 document.querySelectorAll('[data-confirm]').forEach(el=>el.addEventListener('click',(e)=>{if(!confirm(el.dataset.confirm||'Подтвердить действие?'))e.preventDefault();}));
 for(const form of document.querySelectorAll('form')){form.addEventListener('submit',()=>{const btn=form.querySelector('button[type="submit"]');if(btn&&!btn.dataset.submitted){btn.dataset.submitted='1';btn.style.opacity='.7';}})}
})();
