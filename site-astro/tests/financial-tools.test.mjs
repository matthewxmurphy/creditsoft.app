import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { calculate, utilization, simulateDebt } from '../src/lib/financial-tools/model.mjs';
import { tools, sourceLink } from '../src/lib/financial-tools/catalog.mjs';

test('all twelve tools produce useful default results',()=>{
 assert.equal(tools.length,12);
 for(const tool of tools){
  const values=Object.fromEntries(tool.fields.map(f=>[f.name,f.type==='select'?f.options[0][0]:f.type==='checkboxes'?[]:f.value]));
  const rows=tool.rows?[1,2].map(()=>Object.fromEntries(tool.rows.fields.map(f=>[f.name,f.value]))):[];
  const r=calculate(tool.slug,values,rows);
  assert.ok(r.headline,tool.slug);assert.ok(r.summary,tool.slug);
  assert.ok(!/NaN|Infinity/.test(JSON.stringify(r)),tool.slug);
 }
});
test('budget preserves a shortfall rather than clamping it to zero',()=>{
 assert.equal(calculate('budget',{income:1000,essential:800,debt:300,savings:100}).headline,'-$200.00');
});
test('utilization does not count a card twice and handles target boundaries',()=>{
 const u=utilization(3000,10000,10);assert.equal(u.percent,30);assert.equal(u.paydown,2000);
 assert.equal(utilization(6000,5000).percent,120);
 assert.equal(utilization(500,10000,30).paydown,0);
 const r=calculate('optimizer',{target:30},[{balance:3000,limit:10000},{balance:500,limit:5000}]);
 assert.equal(r.headline,'23.3%');assert.deepEqual(r.metrics[0],['Total balance','$3,500.00']);
});
test('invalid numeric values cannot become a healthy ratio',()=>{
 for(const x of ['',NaN,Infinity,-1])assert.throws(()=>utilization(x,10000));
 assert.throws(()=>utilization(100,0));assert.throws(()=>utilization(100,1000,101));
 assert.throws(()=>calculate('simulator',{balance:100,limit:1000,paydown:101}));
});
test('zero-interest payoff, final partial payment and monthly interest',()=>{
 const a=simulateDebt([{balance:100,apr:0,minimum:30}]);
 assert.equal(a.months,4);assert.equal(a.totalPaid,100);assert.equal(a.interest,0);assert.equal(a.remaining,0);
 const b=simulateDebt([{balance:100,apr:12,minimum:60}]);
 assert.equal(b.months,2);assert.equal(b.interest,1.41);assert.equal(b.totalPaid,101.41);
});
test('unused payment and freed minimums roll into the next account',()=>{
 for(const method of ['snowball','avalanche']){
  const r=simulateDebt([{balance:50,apr:0,minimum:20},{balance:100,apr:0,minimum:20}],60,method);
  assert.equal(r.months,2);assert.equal(r.totalPaid,150);assert.equal(r.budget,100);
 }
 const r=simulateDebt([{balance:50,apr:0,minimum:50},{balance:250,apr:0,minimum:10}],0);
 assert.equal(r.months,5);assert.equal(r.totalPaid,300);
});
test('both debt strategies use equal budgets and account for every dollar',()=>{
 const rows=[{balance:2000,apr:24,minimum:60},{balance:500,apr:8,minimum:25}];
 const a=simulateDebt(rows,100,'avalanche'),s=simulateDebt(rows,100,'snowball');
 assert.equal(a.budget,s.budget);assert.ok(a.interest<s.interest);
 for(const r of [a,s])assert.ok(Math.abs(r.totalPaid-(r.principal+r.interest-r.remaining))<0.011);
});
test('unfinished plans never claim a payoff date',()=>{
 const r=simulateDebt([{balance:100000,apr:0,minimum:1}]);
 assert.equal(r.paidOff,false);assert.equal(r.months,600);assert.equal(r.remaining,99400);
 assert.match(calculate('payoff',{balance:1000,apr:12,payment:1,extra:0}).headline,/Not paid off/);
 assert.throws(()=>simulateDebt([{balance:10,apr:0,minimum:0}],0));
 assert.equal(calculate('payoff',{balance:100,apr:0,payment:0,extra:50}).headline,'2 months');
 assert.equal(simulateDebt([{balance:0,apr:0,minimum:0}]).months,0);
});
test('scenario planner reports ratios, never invented credit-score points',()=>{
 const r=calculate('simulator',{balance:4000,limit:10000,paydown:1500});
 assert.equal(r.headline,'40.0% → 25.0%');assert.match(r.summary,/does not predict a credit score/);
});
test('readiness counts preparation only, without an approval claim',()=>{
 const r=calculate('readiness',{check0:'yes',check1:'yes'});
 assert.equal(r.headline,'2 of 6 checks completed');assert.equal(r.items.length,4);
});
test('notes remain text and malformed entry counts fail',()=>{
 const r=calculate('prep',{questions:'<img src=x onerror=alert(1)>'});
 assert.ok(r.items[0].includes('<img'));
 const client=readFileSync(new URL('../src/lib/financial-tools/client.mjs',import.meta.url),'utf8');
 assert.ok(!client.includes('innerHTML'));assert.ok(!/fetch\(|localStorage|sessionStorage/.test(client));
 assert.throws(()=>calculate('negative',{collections:1.5,chargeoffs:0,lates:0}));
 assert.throws(()=>calculate('timeline',{closeDay:2.5}));
});
test('built tools ship normal HTML backlinks, labels, canonicals and no trackers',()=>{
 for(const slug of ['',...tools.map(t=>t.slug+'/')]){
  const html=readFileSync(new URL(`../dist/tools/${slug}index.html`,import.meta.url),'utf8');
  assert.ok(html.includes(`href="${sourceLink}"`),slug);
  assert.match(html,/>Credit Sense Credit Repair<\/a>/,slug);
  assert.ok(html.includes(`href="https://www.creditsoft.app/tools/${slug}"`),slug);
  assert.ok(!/googletagmanager|fbevents|facebook.com\/tr\?/.test(html),slug);
  assert.ok(!/noindex/.test(html),slug);
 }
 const sitemap=readFileSync(new URL('../dist/sitemap.xml',import.meta.url),'utf8');
 for(const tool of tools)assert.ok(sitemap.includes(`/tools/${tool.slug}/`));
 const home=readFileSync(new URL('../dist/index.html',import.meta.url),'utf8');
 assert.ok(home.includes('href="/tools/"'));
});
