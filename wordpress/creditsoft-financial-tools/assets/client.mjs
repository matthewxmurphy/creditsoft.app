import { calculate } from './model.mjs';
const root=document.querySelector('[data-financial-tool]');
if(root){
 const form=root.querySelector('form'),error=root.querySelector('[data-tool-error]');
 const headline=root.querySelector('[data-result-headline]'),summary=root.querySelector('[data-result-summary]');
 const metrics=root.querySelector('[data-result-metrics]'),items=root.querySelector('[data-result-items]');
 const calculateButton=root.querySelector('[data-calculate]'),printButton=root.querySelector('[data-print]');
 const rowList=root.querySelector('[data-tool-rows]'),addButton=root.querySelector('[data-add-row]');
 const initialRows=rowList?Array.from(rowList.children).map(row=>row.cloneNode(true)):[];
 let counter=2;
 const read=()=>{
  const v={};
  for(const field of form.querySelectorAll('input,select,textarea')){
   if(field.closest('[data-tool-row]'))continue;
   if(field.type==='checkbox'){v[field.name]??=[];if(field.checked)v[field.name].push(field.value);}
   else v[field.name]=field.value;
  }
  const rows=Array.from(form.querySelectorAll('[data-tool-row]')).map(row=>Object.fromEntries(Array.from(row.querySelectorAll('input')).map(input=>[input.name,input.value])));
  return {v,rows};
 };
 const showError=message=>{error.textContent=message;error.hidden=false;headline.textContent='Check your inputs';summary.textContent='Update the inputs, then calculate again.';metrics.replaceChildren();items.replaceChildren();printButton.disabled=true;};
 const run=()=>{
  if(!form.checkValidity()){showError('Complete the highlighted fields with values in the allowed range.');form.reportValidity();return;}
  try{
   const {v,rows}=read(),r=calculate(root.dataset.financialTool,v,rows);
   error.hidden=true;error.textContent='';headline.textContent=r.headline;summary.textContent=r.summary;
   metrics.replaceChildren();items.replaceChildren();
   for(const [label,value] of r.metrics){const group=document.createElement('div'),dt=document.createElement('dt'),dd=document.createElement('dd');dt.textContent=label;dd.textContent=value;group.append(dt,dd);metrics.append(group);}
   for(const text of r.items){const li=document.createElement('li');li.textContent=text;items.append(li);}
   printButton.disabled=false;
  }catch(e){showError(e instanceof Error?e.message:'Check the entered values.');}
 };
 const markChanged=()=>{summary.textContent='Inputs changed. Update the result to recalculate.';printButton.disabled=true;};
 form.addEventListener('submit',e=>{e.preventDefault();run();});
 form.addEventListener('input',markChanged);
 form.addEventListener('change',markChanged);
 calculateButton.addEventListener('click',run);calculateButton.disabled=false;
 const updateRows=()=>{
  const rows=Array.from(rowList.querySelectorAll('[data-tool-row]'));
  rows.forEach((row,i)=>{row.querySelector('legend').textContent=`${addButton.dataset.rowLabel} ${i+1}`;const remove=row.querySelector('[data-remove-row]');remove.disabled=rows.length<=1;remove.setAttribute('aria-label',`Remove ${addButton.dataset.rowLabel.toLowerCase()} ${i+1}`);});
  addButton.disabled=rows.length>=20;
 };
 if(addButton){
  addButton.addEventListener('click',()=>{
   if(rowList.children.length>=20)return;
   const row=root.querySelector('[data-row-template]').content.firstElementChild.cloneNode(true);counter++;
   for(const field of row.querySelectorAll('.tool-field')){const input=field.querySelector('input');input.id=`row-${counter}-${input.name}`;field.querySelector('label').htmlFor=input.id;}
   rowList.append(row);updateRows();markChanged();row.querySelector('input').focus();
  });
  rowList.addEventListener('click',e=>{const button=e.target.closest('[data-remove-row]');if(!button||rowList.children.length<=1)return;button.closest('[data-tool-row]').remove();updateRows();markChanged();addButton.focus();});
  updateRows();
 }
 form.addEventListener('reset',()=>{setTimeout(()=>{if(rowList){rowList.replaceChildren(...initialRows.map(row=>row.cloneNode(true)));updateRows();}run();},0);});
 printButton.addEventListener('click',()=>window.print());
 run();
}
