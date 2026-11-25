// Exceptions module: local demo only; no production employee records are connected.
let exceptions = [
 {id:1,pin:"001",name:"Ahmad Fauzi",type:"Izin",start:"2026-11-20",end:"2026-11-20",time:"",reason:"Keperluan keluarga",status:"Disetujui"},
 {id:2,pin:"002",name:"Siti Aminah",type:"Ganti jadwal",start:"2026-11-22",end:"2026-11-22",time:"",reason:"Penyesuaian kegiatan",status:"Menunggu"},
 {id:3,pin:"003",name:"Budi Santoso",type:"Lembur",start:"2026-11-23",end:"2026-11-23",time:"16:00-18:00",reason:"Pemeliharaan jaringan",status:"Disetujui"}
];
let exceptionId=4;
const exceptionTypes=["Izin","Sakit","Cuti","Cuti kolektif","Ganti jadwal","Ganti jam kerja","Tukar jam kerja","Lembur","Kerja ekstra per hari"];
const escEx=value=>String(value??"").replace(/[&<>"]/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c]));
function exceptionPage(){
 return `<section class="exception-page">
 <aside class="exception-sidebar"><strong>Jenis pengecualian</strong>${exceptionTypes.map((x,i)=>`<button class="exception-type ${i===0?"selected":""}" data-type="${x}">${x}</button>`).join("")}<button class="exception-type" data-type="Semua">Semua pengecualian</button></aside>
 <section class="exception-main">
  <header class="page-heading"><h1>Pengecualian</h1><p>Kelola izin, cuti, perubahan jam/jadwal kerja, dan lembur.</p></header>
  <div class="exception-toolbar"><button class="action-button primary" id="add-exception">＋ Tambah Pengecualian</button><button class="action-button" id="export-exceptions">⇩ Export CSV</button><button class="action-button" id="print-exceptions">Cetak</button></div>
  <div class="exception-filters"><label>Cari pegawai / PIN<input id="exception-search" placeholder="Nama atau PIN"></label><label>Status<select id="exception-status"><option value="">Semua</option><option>Menunggu</option><option>Disetujui</option><option>Ditolak</option></select></label><label>Dari tanggal<input id="exception-from" type="date"></label><label>Sampai tanggal<input id="exception-to" type="date"></label></div>
  <div class="exception-table-wrap"><table class="exception-table"><thead><tr><th>ID</th><th>PIN</th><th>Nama</th><th>Jenis</th><th>Tanggal mulai</th><th>Tanggal akhir</th><th>Jam</th><th>Keterangan</th><th>Status</th><th>Aksi</th></tr></thead><tbody id="exception-rows"></tbody></table></div>
  <div class="table-summary"><span id="exception-count"></span><span>Data demo sementara — belum tersimpan ke database</span></div>
 </section>
 <dialog id="exception-dialog" class="employee-dialog"><form id="exception-form">
  <div class="dialog-head"><strong id="exception-dialog-title">Tambah Pengecualian</strong><button type="button" id="exception-close">×</button></div>
  <div class="form-grid">
   <label>PIN pegawai *<input name="pin" required></label><label>Nama pegawai *<input name="name" required></label>
   <label>Jenis *<select name="type" required>${exceptionTypes.map(x=>`<option>${x}</option>`).join("")}</select></label>
   <label>Status<select name="status"><option>Menunggu</option><option>Disetujui</option><option>Ditolak</option></select></label>
   <label>Tanggal mulai *<input name="start" type="date" required></label><label>Tanggal akhir *<input name="end" type="date" required></label>
   <label>Jam / rentang waktu<input name="time" placeholder="Contoh: 16:00-18:00"></label>
   <label class="full-field">Alasan / keterangan<textarea name="reason" rows="3"></textarea></label>
  </div>
  <p class="form-hint">Contoh lokal. Persetujuan belum memengaruhi hasil perhitungan absensi.</p>
  <div class="dialog-actions"><button type="button" class="action-button" id="exception-cancel">Batal</button><button class="action-button primary" type="submit">Simpan</button></div>
 </form></dialog></section>`;
}
function initExceptionPage(){
 const root=document.querySelector(".exception-page");if(!root)return;
 const $=id=>document.getElementById(id);
 let rows=[],employees=[],selectedType="Izin";
 const typeMap={"Izin":"leave","Sakit":"sick","Cuti":"annual_leave","Cuti kolektif":"collective_leave","Ganti jadwal":"schedule_change","Ganti jam kerja":"hours_change","Tukar jam kerja":"shift_swap","Lembur":"overtime","Kerja ekstra per hari":"extra_work"};
 const typeLabel=Object.fromEntries(Object.entries(typeMap).map(([a,b])=>[b,a]));
 const statusLabel={pending:"Menunggu",approved:"Disetujui",rejected:"Ditolak",draft:"Draft",cancelled:"Dibatalkan"};
 function render(){
  const q=$("exception-search").value.toLowerCase(),st=$("exception-status").value,from=$("exception-from").value,to=$("exception-to").value;
  const filtered=rows.filter(x=>(selectedType==="Semua"||typeLabel[x.exception_type]===selectedType)&&(!q||(String(x.pin)+" "+String(x.full_name)).toLowerCase().includes(q))&&(!st||statusLabel[x.status]===st)&&(!from||String(x.end_at).slice(0,10)>=from)&&(!to||String(x.start_at).slice(0,10)<=to));
  $("exception-rows").innerHTML=filtered.map(x=>{const canDecide=x.status==="pending"&&CfApi.user?.role==="admin";return `<tr><td>${x.id}</td><td>${escEx(x.pin)}</td><td>${escEx(x.full_name)}</td><td>${typeLabel[x.exception_type]||x.exception_type}</td><td>${String(x.start_at).slice(0,10)}</td><td>${String(x.end_at).slice(0,10)}</td><td>${String(x.start_at).slice(11,16)}–${String(x.end_at).slice(11,16)}</td><td>${escEx(x.reason||"")}</td><td><span class="status-pill ${x.status==="approved"?"is-active":""}">${statusLabel[x.status]||x.status}</span></td><td>${canDecide?`<button class="table-action" data-decision="approved" data-id="${x.id}">Setujui</button> <button class="table-action" data-decision="rejected" data-id="${x.id}">Tolak</button>`:"—"}</td></tr>`;}).join("")||'<tr><td colspan="10" class="no-data">Tidak ada data pengecualian.</td></tr>';
  $("exception-count").textContent=`${filtered.length} dari ${rows.length} data database`;
 }
 async function load(){ $("exception-count").textContent="Memuat data database...";try{const [a,b]=await Promise.all([CfApi.request("/exceptions"),CfApi.request("/employees")]);rows=a.data||[];employees=b.data||[];render();}catch(e){$("exception-count").textContent="Gagal memuat: "+e.message;}}
 function openForm(){const f=$("exception-form");f.reset();f.elements.type.value=selectedType==="Semua"?"Izin":selectedType;f.elements.status.value="Menunggu";const pin=f.elements.pin;pin.setAttribute("list","exception-employee-options");let dl=$("exception-employee-options");if(!dl){dl=document.createElement("datalist");dl.id="exception-employee-options";pin.after(dl);}dl.innerHTML=employees.map(e=>`<option value="${escEx(e.pin)}">${escEx(e.full_name)}</option>`).join("");$("exception-dialog").showModal();}
 root.addEventListener("input",render);root.addEventListener("change",render);
 root.addEventListener("click",async e=>{
  const type=e.target.closest("[data-type]");if(type){selectedType=type.dataset.type;root.querySelectorAll(".exception-type").forEach(b=>b.classList.toggle("selected",b===type));render();return;}
  const decision=e.target.closest("[data-decision]");if(decision){if(!confirm((decision.dataset.decision==="approved"?"Setujui":"Tolak")+" pengajuan ini?"))return;try{await CfApi.request("/exceptions/"+decision.dataset.id+"/decision",{method:"POST",body:JSON.stringify({status:decision.dataset.decision})});await load();}catch(err){alert(err.message);}}
 });
 $("add-exception").onclick=openForm;const close=()=>$("exception-dialog").close();$("exception-close").onclick=close;$("exception-cancel").onclick=close;
 $("exception-form").onsubmit=async e=>{e.preventDefault();const d=Object.fromEntries(new FormData(e.target));if(d.end<d.start)return alert("Tanggal akhir tidak boleh lebih awal dari tanggal mulai.");const emp=employees.find(x=>String(x.pin)===String(d.pin));if(!emp)return alert("PIN tidak ditemukan di database pegawai.");const time=d.time.trim();let start=d.start+" 00:00:00",end=d.end+" 23:59:59";if(time){const m=time.match(/^(\\d{2}:\\d{2})\\s*-\\s*(\\d{2}:\\d{2})$/);if(!m)return alert("Format jam harus HH:MM-HH:MM.");start=d.start+" "+m[1]+":00";end=d.end+" "+m[2]+":00";}try{await CfApi.request("/exceptions",{method:"POST",body:JSON.stringify({employee_id:emp.id,exception_type:typeMap[d.type],start_at:start,end_at:end,reason:d.reason||null})});close();await load();}catch(err){alert(err.message);}};
 $("export-exceptions").onclick=()=>{const cols=["id","pin","full_name","exception_type","start_at","end_at","reason","status"];const data=[cols.join(","),...rows.map(x=>cols.map(k=>'"'+String(x[k]??"").replace(/"/g,'""')+'"').join(","))];const a=document.createElement("a");a.href=URL.createObjectURL(new Blob(["\\ufeff"+data.join("\\r\\n")],{type:"text/csv;charset=utf-8"}));a.download="clonefringer-pengecualian.csv";a.click();URL.revokeObjectURL(a.href);};
 $("print-exceptions").onclick=()=>window.print();load();
}
