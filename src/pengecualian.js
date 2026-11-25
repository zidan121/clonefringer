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
 const $=id=>document.getElementById(id);let selectedType="Izin",editingId=null;
 function filtered(){const q=$("exception-search").value.toLowerCase(),st=$("exception-status").value,from=$("exception-from").value,to=$("exception-to").value;
 return exceptions.filter(x=>(selectedType==="Semua"||x.type===selectedType)&&(!q||(x.pin+" "+x.name).toLowerCase().includes(q))&&(!st||x.status===st)&&(!from||x.end>=from)&&(!to||x.start<=to));}
 function render(){const rows=filtered();$("exception-rows").innerHTML=rows.map(x=>`<tr><td>${x.id}</td><td>${escEx(x.pin)}</td><td>${escEx(x.name)}</td><td>${escEx(x.type)}</td><td>${escEx(x.start)}</td><td>${escEx(x.end)}</td><td>${escEx(x.time)}</td><td>${escEx(x.reason)}</td><td><span class="status-pill ${x.status==="Disetujui"?"is-active":""}">${escEx(x.status)}</span></td><td><button class="table-action" data-edit="${x.id}">Ubah</button> <button class="table-action" data-delete="${x.id}">Hapus</button></td></tr>`).join("")||'<tr><td colspan="10" class="no-data">Tidak ada data pengecualian.</td></tr>';$("exception-count").textContent=`${rows.length} dari ${exceptions.length} data`;}
 function openForm(item){editingId=item?.id??null;$("exception-dialog-title").textContent=item?"Ubah Pengecualian":"Tambah Pengecualian";const form=$("exception-form");form.reset();for(const [k,v] of Object.entries(item||{})){if(form.elements[k])form.elements[k].value=v;}if(!item){form.elements.type.value=selectedType==="Semua"?"Izin":selectedType;form.elements.status.value="Menunggu";} $("exception-dialog").showModal();}
 root.addEventListener("input",render);root.addEventListener("change",render);
 root.addEventListener("click",e=>{const type=e.target.closest("[data-type]");if(type){selectedType=type.dataset.type;root.querySelectorAll(".exception-type").forEach(b=>b.classList.toggle("selected",b===type));render();return;}const edit=e.target.closest("[data-edit]");if(edit){openForm(exceptions.find(x=>x.id===Number(edit.dataset.edit)));return;}const del=e.target.closest("[data-delete]");if(del&&confirm("Hapus data pengecualian contoh ini?")){exceptions=exceptions.filter(x=>x.id!==Number(del.dataset.delete));render();}});
 $("add-exception").onclick=()=>openForm(null);const close=()=>$("exception-dialog").close();$("exception-close").onclick=close;$("exception-cancel").onclick=close;
 $("exception-form").onsubmit=e=>{e.preventDefault();const data=Object.fromEntries(new FormData(e.target));if(data.end<data.start)return alert("Tanggal akhir tidak boleh lebih awal dari tanggal mulai.");if(editingId)exceptions=exceptions.map(x=>x.id===editingId?{...data,id:editingId}:x);else exceptions.push({...data,id:exceptionId++});close();render();};
 $("export-exceptions").onclick=()=>{const cols=["id","pin","name","type","start","end","time","reason","status"];const rows=[cols.join(","),...filtered().map(x=>cols.map(k=>'"'+String(x[k]??"").replace(/"/g,'""')+'"').join(","))];const a=document.createElement("a");a.href=URL.createObjectURL(new Blob(["\ufeff"+rows.join("\r\n")],{type:"text/csv;charset=utf-8"}));a.download="clonefringer-pengecualian.csv";a.click();URL.revokeObjectURL(a.href);};
 $("print-exceptions").onclick=()=>window.print();render();
}
