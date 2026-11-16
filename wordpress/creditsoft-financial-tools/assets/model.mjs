// Portable, deterministic calculations. No storage, network access or customer data.
export const money = n => new Intl.NumberFormat('en-US', {style:'currency',currency:'USD',maximumFractionDigits:2}).format(n);
const pct = n => `${n.toFixed(1)}%`;
const cents = n => Math.round(n * 100);
const sum = list => list.reduce((a,b)=>a+b,0);
function num(value, label, min=0, max=100000000) {
 if (value === '' || value === null || value === undefined || typeof value === 'boolean') throw new Error(`Enter ${label.toLowerCase()}.`);
 const n=Number(value);
 if (!Number.isFinite(n) || n<min || n>max) throw new Error(`${label} must be between ${min} and ${max}.`);
 return n;
}
export function utilization(balance, limit, target=30) {
 balance=num(balance,'Balance'); limit=num(limit,'Credit limit',0.01); target=num(target,'Target',0,100);
 return {balance,limit,percent:balance/limit*100,paydown:Math.max(0,balance-limit*target/100)};
}
export function simulateDebt(rows, extra=0, method='avalanche') {
 if (!['avalanche','snowball'].includes(method)) throw new Error('Choose a supported debt strategy.');
 if (!Array.isArray(rows)||rows.length===0||rows.length>20) throw new Error('Enter between 1 and 20 debts.');
 const accounts=rows.map((d,i)=>({name:`Debt ${i+1}`,balance:cents(num(d.balance,'Balance')),apr:num(d.apr,'APR',0,100),minimum:cents(num(d.minimum,'Minimum payment'))})).filter(d=>d.balance>0);
 const principal=sum(accounts.map(d=>d.balance));
 const budget=sum(accounts.map(d=>d.minimum))+cents(num(extra,'Extra payment'));
 if(principal>0&&budget===0) throw new Error('Enter a monthly payment greater than zero.');
 let months=0,interest=0,totalPaid=0;
 const order=[];
 while(accounts.some(d=>d.balance>0)&&months<600){
  months++;
  for(const a of accounts){const charge=Math.round(a.balance*a.apr/1200);a.balance+=charge;interest+=charge;}
  let remaining=budget;
  for(const a of accounts){const payment=Math.min(a.balance,a.minimum);a.balance-=payment;remaining-=payment;totalPaid+=payment;}
  const active=accounts.filter(a=>a.balance>0).sort((a,b)=>method==='avalanche'?b.apr-a.apr||a.balance-b.balance:a.balance-b.balance||b.apr-a.apr);
  for(const a of active){
   if(remaining<=0)break;
   const payment=Math.min(a.balance,remaining);a.balance-=payment;remaining-=payment;totalPaid+=payment;
  }
  for(const a of accounts)if(a.balance===0&&!order.includes(a.name))order.push(a.name);
 }
 const remaining=sum(accounts.map(a=>a.balance));
 return {paidOff:remaining===0,months,interest:interest/100,totalPaid:totalPaid/100,remaining:remaining/100,principal:principal/100,budget:budget/100,order};
}
const reviews={
 'High card balances':'List balances and limits; compare a manageable paydown with your available monthly cash.',
 'Late payments':'Check reported dates against your records and arrange reminders for upcoming payments.',
 'Unfamiliar or incorrect information':'Compare the entry with your own documents and note exactly what appears inaccurate.',
 'Collections or charge-offs':'Gather statements and notices to discuss account ownership, amounts and reporting accuracy.',
 'Short credit history':'Review the history you already have and compare costs before adding an account.',
 'Recent applications':'List recent application dates and the purpose of any planned application.',
};
const result=(headline,summary,metrics=[],items=[])=>({headline,summary,metrics,items});
const payoffLabel=r=>r.paidOff?`${r.months} months`:'Not paid off within 600 months';
export function calculate(slug,v={},rows=[]){
 switch(slug){
  case 'budget':{
   const income=num(v.income,'Income'),expenses=num(v.essential,'Essential bills')+num(v.debt,'Debt payments')+num(v.savings,'Savings target');
   const cushion=income-expenses;
   return result(money(cushion),cushion>=0?'Monthly cash remaining after the amounts you entered.':'Monthly shortfall: entered commitments exceed take-home income.',[['Take-home income',money(income)],['Bills, debt and savings',money(expenses)]]);
  }
  case 'payoff':{
   const debt={balance:v.balance,apr:v.apr,minimum:v.payment};
   const r=simulateDebt([debt],v.extra),base=Number(v.payment)>0||Number(v.balance)===0?simulateDebt([debt],0):{paidOff:false};
   const items=r.paidOff?[`Compared with your base payment: ${base.paidOff?`${base.months-r.months} fewer months and ${money(Math.max(0,base.interest-r.interest))} less interest.`:'the base plan does not clear the balance within 600 months.'}`]:['Increase the payment or review the APR; no payoff date is shown for an unfinished plan.'];
   return result(payoffLabel(r),'Estimate with your extra payment, assuming the same payment budget each month.',[[r.paidOff?'Estimated total interest':'Interest over 600 months',money(r.interest)],[r.paidOff?'Total paid':'Amount paid over 600 months',money(r.totalPaid)],['Balance remaining',money(r.remaining)],['Monthly budget',money(r.budget)]],items);
  }
  case 'utilization':{
   const r=utilization(v.balance,v.limit,v.target);
   return result(pct(r.percent),'Overall revolving utilization.',[['Paydown to selected target',money(r.paydown)],['Target balance',money(r.limit*Number(v.target)/100)]],r.percent>100?['Your entered balance exceeds your credit limit. Check both values.']:[]);
  }
  case 'optimizer':{
   if(!rows.length||rows.length>20)throw new Error('Enter between 1 and 20 cards.');
   const target=num(v.target,'Target',0,100);
   const cards=rows.map((r,i)=>({...utilization(r.balance,r.limit,target),name:`Card ${i+1}`}));
   const all=utilization(sum(cards.map(r=>r.balance)),sum(cards.map(r=>r.limit)),target);
   const perCard=sum(cards.map(r=>r.paydown));
   return result(pct(all.percent),'Combined utilization; each card is counted once.',[['Total balance',money(all.balance)],['Total limit',money(all.limit)],['Paydown for overall target',money(all.paydown)],['Paydown for every card’s target',money(perCard)]],cards.sort((a,b)=>b.percent-a.percent).map(r=>`${r.name}: ${pct(r.percent)} utilization; ${money(r.paydown)} to reach ${target}%.`));
  }
  case 'simulator':{
   const current=utilization(v.balance,v.limit),paydown=num(v.paydown,'Paydown',0,current.balance),after=utilization(current.balance-paydown,current.limit);
   return result(`${pct(current.percent)} → ${pct(after.percent)}`,'Utilization before and after your proposed paydown. This does not predict a credit score.',[['Proposed paydown',money(paydown)],['Remaining balance',money(after.balance)],['Utilization change',`${(current.percent-after.percent).toFixed(1)} percentage points`]],['Reporting timing and the rest of your credit file can affect what appears in an actual score.']);
  }
  case 'prep':{
   const fields=[['goal','Goal'],['timeline','Time frame'],['balances','Balances or payments to discuss'],['questions','Questions']];
   const items=fields.filter(([k])=>String(v[k]||'').trim()).map(([k,label])=>`${label}: ${String(v[k]).trim()}`);
   return result(`${items.length} of 4 prompts completed`,'Your consultation worksheet stays in this page. Use Print worksheet for a paper copy.',[],items.length?items:['Add your goals and questions to build the worksheet.']);
  }
  case 'strategy':{
   const selected=Array.isArray(v.issues)?v.issues:[];
   return result(`Plan for ${v.horizon}`,`Goal: ${v.goal}. Use these topics to prepare for a discussion, not as a prediction of approval.`,[],selected.length?selected.filter(i=>reviews[i]).map(i=>reviews[i]):['Review your reports, monthly budget and the requirements relevant to your goal.']);
  }
  case 'readiness':{
   const labels=['Credit reports','Revolving balances and limits','Monthly payment budget','Payment history','Recent applications','Lender documentation'];
   const count=labels.filter((_,i)=>v[`check${i}`]==='yes').length;
   return result(`${count} of 6 checks completed`,'Preparation progress only. This is not a bureau score or lending decision.',[],labels.filter((_,i)=>v[`check${i}`]!=='yes').map(label=>`Review: ${label}.`).concat(count===6?['You have checked each preparation topic. Confirm current requirements directly with the lender.']:[]));
  }
  case 'negative':{
   const entries=[['collections','Collections'],['chargeoffs','Charge-offs'],['lates','Late-payment entries']].map(([k,label])=>[label,num(v[k],label,0,100)]);
   if(entries.some(([,n])=>!Number.isInteger(n)))throw new Error('Enter whole numbers for report entries.');
   const total=sum(entries.map(([,n])=>n));
   const items=entries.filter(([,n])=>n>0).map(([label,n])=>`${label}: ${n}. Compare dates, ownership, balances and supporting records.`);
   items.push(v.accuracy==='Yes'?'Document the specific inaccuracy and its supporting records before using an official dispute process.':'First check accuracy against your own records. An unfavorable entry is not necessarily inaccurate.');
   if(String(v.notes||'').trim())items.push(`Your notes: ${String(v.notes).trim()}`);
   return result(`${total} entries to review`,'This count may include overlapping accounts. It does not measure score impact or predict removal.',[],items);
  }
  case 'comparison':{
   const a=simulateDebt(rows,v.extra,'avalanche'),s=simulateDebt(rows,v.extra,'snowball');
   const metrics=[['Monthly budget for either plan',money(a.budget)],['Avalanche payoff',payoffLabel(a)],['Avalanche interest'+(a.paidOff?'':' over 600 months'),money(a.interest)],['Snowball payoff',payoffLabel(s)],['Snowball interest'+(s.paidOff?'':' over 600 months'),money(s.interest)]];
   const complete=a.paidOff&&s.paidOff;
   return result(complete?'Compare the two payoff paths':'At least one plan remains unpaid',complete?`Estimated interest difference: ${money(Math.abs(s.interest-a.interest))}${a.interest===s.interest?' (a tie).':a.interest<s.interest?' less with avalanche.':' less with snowball.'}`:'No debt-free date is claimed for a plan that exceeds 600 months.',metrics,[`Avalanche remaining balance: ${money(a.remaining)}.`,`Snowball remaining balance: ${money(s.remaining)}.`]);
  }
  case 'timeline':{
   const day=num(v.closeDay,'Closing day',1,31);if(!Number.isInteger(day))throw new Error('Enter a whole-number closing day.');
   return result(`Your ${v.horizon.toLowerCase()} planning window`,`Goal: ${v.goal}.`,[],[`Now: confirm payment due dates and statement closing dates. Your entered closing day is ${day}; the due date may be different.`,`Before the next statement closes: review the budget and any planned balance payment.`,`At the next reporting review: compare the updated report with your records.`,v.followup==='Yes'?'Pending follow-up: use the dates and instructions in the actual notice; this planner does not calculate a legal deadline.':'At the end of your planning window: review progress and update your next steps.']);
  }
  case 'recommendation':{
   const notes={
    'Secured credit card':['How much is the refundable deposit, and when can it be returned?','What APR and fees apply? A deposit does not replace the monthly payment.'],
    'Credit-builder loan':['When are the borrowed funds available to you?','What are the total interest, fees and monthly payments?'],
    'Unsecured credit card':['What APR, annual fee and other charges apply?','Can you check terms before an application that may require a credit inquiry?'],
    'Business account':['Is a personal guarantee required?','Which business or consumer reporting agencies receive account information?'],
   };
   return result(v.category,`Comparison focus: ${v.priority}.`,[],[...(notes[v.category]||[]),'Which credit reporting agencies receive payment information?','Can the payment fit your budget without new borrowing?','Read the actual terms; this worksheet does not endorse a provider or predict approval.']);
  }
  default:throw new Error('This tool is not available.');
 }
}
