'use strict';
(()=>{
 const toggle=document.querySelector('.menu-toggle');
 const mobile=document.querySelector('#mobile-menu');
 if(toggle&&mobile){
  toggle.addEventListener('click',()=>{
   const open=toggle.getAttribute('aria-expanded')==='true';
   toggle.setAttribute('aria-expanded',String(!open));
   toggle.setAttribute('aria-label',open?'Открыть меню':'Закрыть меню');
   mobile.hidden=open;
  });
  mobile.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>{mobile.hidden=true;toggle.setAttribute('aria-expanded','false');toggle.setAttribute('aria-label','Открыть меню');}));
  window.addEventListener('resize',()=>{if(window.innerWidth>1060){mobile.hidden=true;toggle.setAttribute('aria-expanded','false');}});
 }
 let toastTimer;
 function toast(message){const el=document.getElementById('toast');if(!el)return;clearTimeout(toastTimer);el.textContent=message;el.classList.add('is-showing');toastTimer=setTimeout(()=>el.classList.remove('is-showing'),3200);}
 async function copyText(value){try{await navigator.clipboard.writeText(value);toast('Адрес скопирован: '+value);}catch{toast('Адрес сервера: '+value);}}
 const list=document.getElementById('servers-list');
 if(!list)return;
 const refresh=document.getElementById('refresh-status');
 const configured=parseInt(list.dataset.configured||'0',10);
 if(!configured){if(refresh)refresh.disabled=true;return;}
 function create(tag,cls,text){const el=document.createElement(tag);if(cls)el.className=cls;if(text!==undefined)el.textContent=text;return el;}
 async function loadServers(){
  if(refresh){refresh.disabled=true;refresh.textContent='Обновляем…';}
  try{
   const response=await fetch('/api/servers.php',{cache:'no-store',headers:{'Accept':'application/json'}});
   if(!response.ok)throw new Error('Сервер статуса временно недоступен');
   const json=await response.json();
   if(!Array.isArray(json.servers))throw new Error('Некорректные данные');
   list.replaceChildren();
   for(const server of json.servers){
    const online=server.status==='online';const address=String(server.address||'');
    const card=create('article','server-card');
    const left=create('div','server-left');left.append(create('div','server-badge','V'));
    const info=create('div');info.append(create('h3',null,String(server.name||'VOSTERA SERVER')));info.append(create('p',null,address));left.append(info);card.append(left);
    const actions=create('div','server-card-actions');const state=create('span','server-online'+(online?' is-online':''));state.append(create('i'));state.append(document.createTextNode(online?' Онлайн':' Оффлайн / нет ответа'));
    actions.append(state);
    if(online&&Number.isFinite(server.players)&&Number.isFinite(server.max_players)){
     const count=create('span','player-count');const strong=create('strong',null,String(server.players));count.append(strong);count.append(document.createTextNode(' / '+server.max_players));actions.append(count);
    }
    const button=create('button','button button-small button-outline','Скопировать IP');button.type='button';button.addEventListener('click',()=>copyText(address));actions.append(button);
    card.append(actions);list.append(card);
   }
   if(!json.servers.length){list.append(create('div','loading-state','Серверы не настроены.'));}
  }catch(error){list.replaceChildren(create('div','loading-state','Не удалось получить состояние серверов. Повторите попытку позже.'));}
  finally{if(refresh){refresh.disabled=false;refresh.textContent='Обновить ↻';}}
 }
 if(refresh)refresh.addEventListener('click',loadServers);
 loadServers();
 setInterval(()=>{if(!document.hidden)loadServers();},60000);
})();
