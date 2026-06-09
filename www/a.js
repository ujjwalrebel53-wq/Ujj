let accs=[], poll=null, jobPoll=null, logTimer=null;
const $=id=>document.getElementById(id);
const esc=s=>String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

async function api(p,o={}){
  const r=await fetch(p,{headers:{'Content-Type':'application/json',...o.headers},...o});
  const d=await r.json().catch(()=>({}));
  if(!r.ok)throw new Error(d.error||('HTTP '+r.status));
  return d;
}
function toast(m,err){const t=document.createElement('div');t.className='toast'+(err?' err':'');t.textContent=m;$('toasts').appendChild(t);setTimeout(()=>t.remove(),4000)}
function go(p){document.querySelectorAll('.nav').forEach(n=>n.classList.toggle('on',n.dataset.p===p));document.querySelectorAll('.page').forEach(x=>x.classList.remove('on'));$(''+p).classList.add('on');
  if(p==='dash')loadDash();if(p==='acc')loadAcc();if(p==='create'){loadPx();loadJobs();startPoll()}else stopPoll();if(p==='logs'){loadLogs();startLogs()}else stopLogs()}
document.querySelectorAll('.nav').forEach(n=>n.onclick=()=>go(n.dataset.p));

async function loadDash(){
  try{const s=await api('/api/stats');
  $('stats').innerHTML=[['Total',s.total_accounts],['Active',s.active_accounts],['Errors',s.error_accounts],['Created',s.accounts_created],['In Queue',s.creation_in_progress]].map(([l,v])=>`<div class="card"><div style="color:var(--m);font-size:.8rem">${l}</div><b>${v}</b></div>`).join('')}catch(e){toast(e.message,1)}
}
async function loadAcc(){try{accs=await api('/api/accounts');drawAcc()}catch(e){$('accs').innerHTML='<p>'+esc(e.message)+'</p>'}}
function drawAcc(){
  const q=($('q').value||'').toLowerCase(),f=accs.filter(a=>a.username.includes(q)||(a.full_name||'').toLowerCase().includes(q));
  $('accs').innerHTML=f.length?f.map(a=>`<div class="acc"><h3>@${esc(a.username)}</h3><div class="badge ${a.status==='active'?'ok':a.status==='error'?'err':''}">${a.status}</div><p style="color:var(--m);font-size:.82rem;margin:8px 0">${esc(a.full_name||'')} · ${a.followers||0} followers</p><div class="row"><button class="btn btn-s btn-sm" onclick="loginAcc(${a.id})">Login</button><button class="btn btn-s btn-sm" onclick="refreshAcc(${a.id})">Refresh</button><button class="btn btn-s btn-sm" onclick="delAcc(${a.id})">Delete</button></div></div>`).join(''):'<p style="color:var(--m)">No accounts</p>';
}
function addAcc(){
  $('modal-body').innerHTML=`<h2>Add Account</h2><div class="fg"><label>Username</label><input id="nu"></div><div class="fg"><label>Password</label><input id="np" type="password"></div><div class="fg"><label>Group</label><input id="ng" value="default"></div><div class="row" style="margin-top:14px"><button class="btn btn-s" onclick="closeM()">Cancel</button><button class="btn btn-p" onclick="saveAcc()">Save</button></div>`;
  $('modal').classList.add('on');
}
async function saveAcc(){try{await api('/api/accounts',{method:'POST',body:JSON.stringify({username:$('nu').value,password:$('np').value,group_name:$('ng').value,use_webshare:$('ws').checked})});toast('Added');closeM();loadAcc();loadDash()}catch(e){toast(e.message,1)}}
async function loginAcc(id){try{await api('/api/accounts/'+id+'/login',{method:'POST',body:'{}'});toast('Logged in');loadAcc()}catch(e){toast(e.message,1)}}
async function refreshAcc(id){try{await api('/api/accounts/'+id+'/refresh',{method:'POST',body:'{}'});toast('Refreshed');loadAcc()}catch(e){toast(e.message,1)}}
async function delAcc(id){if(!confirm('Delete?'))return;try{await api('/api/accounts/'+id,{method:'DELETE'});toast('Deleted');loadAcc();loadDash()}catch(e){toast(e.message,1)}}
function closeM(){$('modal').classList.remove('on')}

async function loadPx(){try{const s=await api('/api/proxies/stats');$('px').innerHTML=s.enabled?`Proxy pool: <b style="color:var(--ok)">${s.total_proxies}</b> <button class="btn btn-s btn-sm" onclick="refreshPx()">Refresh</button>`:'Webshare URL .env mein set karo (WEBSHARE_PROXY_URL)'}catch(e){$('px').textContent=e.message}}
async function refreshPx(){try{await api('/api/proxies/refresh',{method:'POST'});loadPx();toast('Proxies refreshed')}catch(e){toast(e.message,1)}}
async function preview(){try{const p=await api('/api/creator/preview?count=3&prefix='+encodeURIComponent($('pre').value));$('modal-body').innerHTML='<h2>Preview</h2>'+p.map(x=>`<div style="margin:8px 0;font-size:.88rem"><b>@${esc(x.username)}</b> ${esc(x.full_name)} <code>${esc(x.password)}</code></div>`).join('')+'<button class="btn btn-p" style="margin-top:12px" onclick="closeM()">OK</button>';$('modal').classList.add('on')}catch(e){toast(e.message,1)}}

function showProg(t,m,w){$('pt').textContent=t;$('pm').textContent=m;$('pb').style.width=(w||15)+'%';$('prog').classList.add('on')}
function hideProg(){$('prog').classList.remove('on');if(jobPoll){clearInterval(jobPoll);jobPoll=null}}

async function pollJob(id){
  const start=Date.now();let n=0;
  const tick=async()=>{n++;
    try{const j=await api('/api/creator/jobs/'+id);const e=Math.floor((Date.now()-start)/1000);
      if(j.status==='pending')showProg('Queue...',`Wait ${e}s — background worker`,25);
      else if(j.status==='creating')showProg('Signup chal raha hai...',`${e}s — 2-5 min normal`,Math.min(88,35+e/2));
      else if(j.status==='success'){hideProg();$('modal-body').innerHTML=`<h2>✅ Created</h2><p>@${esc(j.username)}</p><p><code>${esc(j.password)}</code></p><p>${esc(j.email||'')}</p><button class="btn btn-p" onclick="closeM()">OK</button>`;$('modal').classList.add('on');toast('Done');loadJobs();loadDash();loadAcc();return 1}
      else if(j.status==='waiting_code'){hideProg();$('modal-body').innerHTML=`<h2>OTP Code</h2><input id="vc" maxlength="6"><button class="btn btn-p" style="margin-top:10px" onclick="verifyJob(${id})">Verify</button>`;$('modal').classList.add('on');return 1}
      else if(j.status==='failed'){hideProg();toast(j.error||'Failed',1);loadJobs();return 1}
    }catch(err){if(n>8){hideProg();toast(err.message,1);return 1}}
    return 0};
  if(await tick())return;
  jobPoll=setInterval(async()=>{if(await tick()){clearInterval(jobPoll);jobPoll=null}},5000);
}
async function verifyJob(id){try{await api('/api/creator/jobs/'+id+'/verify',{method:'POST',body:JSON.stringify({code:$('vc').value})});closeM();pollJob(id)}catch(e){toast(e.message,1)}}

async function create1(){try{const r=await api('/api/creator/create',{method:'POST',body:JSON.stringify({username_prefix:$('pre').value,group_name:$('grp').value,use_webshare:$('ws').checked})});toast('Queued');loadJobs();pollJob(r.job_id)}catch(e){toast(e.message,1)}}
async function createBatch(){const n=+$('cnt').value;if(!confirm(n+' accounts?'))return;try{await api('/api/creator/batch',{method:'POST',body:JSON.stringify({count:n,delay_seconds:+$('dly').value,username_prefix:$('pre').value,group_name:$('grp').value,use_webshare:$('ws').checked})});toast('Batch queued');loadJobs()}catch(e){toast(e.message,1)}}

async function loadJobs(){try{const j=await api('/api/creator/jobs?limit=20');
$('jobs').innerHTML=j.length?j.map(x=>{const a=x.status==='pending'||x.status==='creating';return`<div class="job${a?' act':''}"><b>@${esc(x.username)}</b> <span class="badge">${x.status}</span>${x.error?`<div style="color:var(--err);margin-top:4px">${esc(x.error)}</div>`:''}${x.status==='failed'?`<button class="btn btn-s btn-sm" style="margin-top:6px" onclick="retryJob(${x.id})">Retry</button>`:''}</div>`}).join(''):'<p style="color:var(--m)">No jobs</p>'}catch(e){}}
async function retryJob(id){try{await api('/api/creator/jobs/'+id+'/retry',{method:'POST',body:'{}'});pollJob(id);loadJobs()}catch(e){toast(e.message,1)}}
function startPoll(){stopPoll();poll=setInterval(loadJobs,8000)}
function stopPoll(){poll&&clearInterval(poll);poll=null}

async function loadLogs(){try{const l=await api('/api/logs?limit=200');$('logbox').innerHTML=l.map(x=>`<div class="log ${x.level}">[${esc(x.time)}] ${esc(x.message)}</div>`).join('')||'<div class="log">No logs</div>';$('logbox').scrollTop=$('logbox').scrollHeight}catch(e){$('logbox').textContent=e.message}}
function startLogs(){stopLogs();logTimer=setInterval(loadLogs,10000)}
function stopLogs(){logTimer&&clearInterval(logTimer);logTimer=null}
async function clearLogs(){await api('/api/logs/clear',{method:'POST'});loadLogs()}

loadDash();
