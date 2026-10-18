(function(){'use strict';
const root=document.getElementById('tbm-generator');if(!root)return;
const get=id=>document.getElementById('tbm-'+id),kind=get('shortcode-kind'),output=get('generated');
const num=(id,fallback,min,max)=>{const value=Number.parseInt(get(id).value,10);return Math.max(min,Math.min(max,Number.isFinite(value)?value:fallback));};
function update(){const type=kind.value;root.querySelectorAll('[data-generator-panel]').forEach(panel=>{panel.hidden=panel.dataset.generatorPanel!==type;});root.querySelector('[data-generator-days]').hidden=type==='ranking';
if(type==='ranking'){const id=get('ranking-id').value.replace(/[^a-z0-9_-]/g,'');output.value=id?'[tabulamea_ranking id="'+id+'"]':'';}
if(type==='popular')output.value='[tabulamea_popular number="'+num('number',5,1,50)+'" days="'+num('days',30,1,36500)+'" post_type="'+get('post-type').value.replace(/[^a-z0-9_-]/g,'')+'"]';
if(type==='counter')output.value='[tabulamea_counter days="'+num('days',30,1,36500)+'" global="'+get('global').checked+'"]';
get('copy').disabled=!output.value;get('copy-status').textContent='';}
kind.addEventListener('change',()=>{get('days').value='30';update();});
root.addEventListener('input',event=>{if(event.target!==output)update();});root.addEventListener('change',update);
get('copy').addEventListener('click',async()=>{try{if(!navigator.clipboard)throw Error('clipboard');await navigator.clipboard.writeText(output.value);get('copy-status').textContent=get('copy').dataset.copied;}catch(error){output.focus();output.select();get('copy-status').textContent=get('copy').dataset.fallback;}});
update();
})();
