// Employee module: database-backed through CLONEFRINGER API.
const demoEmployees = [
 {pin:"001",name:"Ahmad Fauzi",birth:"1988-04-12",position:"Guru",department:"SDIT",office:"Grobogan",phone:"081234560001",email:"",address:"",gender:"Laki-laki",religion:"Islam",maritalStatus:"Menikah",education:"S1",bloodType:"O",children:2,joinDate:"2020-07-01",contractEnd:"",password:"",rfid:"",privilege:"User",status:"Aktif",schedule:"Reguler",fingerprints:2},
 {pin:"002",name:"Siti Aminah",birth:"1992-09-21",position:"Guru",department:"RAIT",office:"Grobogan",phone:"081234560002",email:"",address:"",gender:"Perempuan",religion:"Islam",maritalStatus:"Menikah",education:"S1",bloodType:"A",children:1,joinDate:"2021-07-01",contractEnd:"",password:"",rfid:"",privilege:"User",status:"Aktif",schedule:"Reguler",fingerprints:2},
 {pin:"003",name:"Budi Santoso",birth:"1985-01-03",position:"Staf IT",department:"Yayasan",office:"Grobogan",phone:"081234560003",email:"",address:"",gender:"Laki-laki",religion:"Islam",maritalStatus:"Menikah",education:"S1",bloodType:"B",children:2,joinDate:"2019-01-01",contractEnd:"",password:"",rfid:"",privilege:"Admin",status:"Aktif",schedule:"Kantor",fingerprints:1},
 {pin:"004",name:"Dewi Lestari",birth:"1990-06-18",position:"Guru",department:"SMPIT",office:"Grobogan",phone:"081234560004",email:"",address:"",gender:"Perempuan",religion:"Islam",maritalStatus:"Menikah",education:"S1",bloodType:"AB",children:1,joinDate:"2022-07-01",contractEnd:"",password:"",rfid:"",privilege:"User",status:"Non Aktif",schedule:"Reguler",fingerprints:0},
 {pin:"005",name:"Rina Wulandari",birth:"1995-12-09",position:"Tata Usaha",department:"KBIT",office:"Grobogan",phone:"081234560005",email:"",address:"",gender:"Perempuan",religion:"Islam",maritalStatus:"Belum Menikah",education:"SMA",bloodType:"O",children:0,joinDate:"2023-07-01",contractEnd:"",password:"",rfid:"",privilege:"User",status:"Aktif",schedule:"Kantor",fingerprints:2}
];
let employees = [];
let employeeApiReady = false;
const employeeIds = new Map();
const employeeStatusToUi = v => ({active:'Aktif',inactive:'Non Aktif',resigned:'Berhenti',terminated:'Berhenti'}[v]||'Aktif');
const employeeStatusToDb = v => ({'Aktif':'active','Non Aktif':'inactive','Berhenti':'resigned'}[v]||'active');
function mapEmployeeFromApi(x){employeeIds.set(String(x.pin),Number(x.id));return {pin:x.pin,name:x.full_name,birth:x.birth_date||'',position:x.position_name||'',department:x.department||x.unit_code||'',office:'',phone:x.phone||'',email:x.email||'',address:x.address||'',gender:x.gender==='male'?'Laki-laki':x.gender==='female'?'Perempuan':'',religion:x.religion||'',maritalStatus:x.marital_status||'',education:x.education||'',bloodType:x.blood_type||'',children:0,joinDate:x.join_date||'',contractEnd:x.contract_end||'',password:'',rfid:x.rfid||'',privilege:x.machine_privilege||'User',status:employeeStatusToUi(x.employee_status),schedule:'Belum ditetapkan',fingerprints:Number(x.fingerprint_count)||0};}
async function loadEmployeesFromApi(){const result=await CfApi.request('/employees');employees=result.data.map(mapEmployeeFromApi);employeeApiReady=true;}
let selectedPins = new Set();
let category = "Aktif";
const esc = value => String(value ?? "").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
function employeePage(){
 return `
 <section class="employee-page">
  <aside class="employee-categories">
   <div class="category-title">Kategori pegawai</div>
   ${["Aktif","Detail pegawai","Non Aktif","Berhenti","Segera habis kontrak"].map((x,i)=>`<button class="category-button ${i===0?"selected":""}" data-category="${x}">${x}</button>`).join("")}
   <div class="category-note">Data database</div>
  </aside>
  <section class="employee-main">
   <div class="employee-filter">
    <div class="filter-title"><strong>Filter data</strong><button id="clear-filters">Bersihkan filter</button></div>
    <div class="filter-grid">
     <label>PIN<input id="filter-pin" placeholder="Cari PIN"></label>
     <label>Nama<input id="filter-name" placeholder="Cari nama pegawai"></label>
     <label>Jadwal<select id="filter-schedule"><option value="">Semua</option><option>Reguler</option><option>Kantor</option></select></label>
     <label>Jabatan<select id="filter-position"><option value="">Semua</option><option>Guru</option><option>Staf IT</option><option>Tata Usaha</option></select></label>
     <label>Departemen<select id="filter-department"><option value="">Semua</option><option>KBIT</option><option>RAIT</option><option>SDIT</option><option>SMPIT</option><option>Yayasan</option></select></label>
     <label>Kantor<select id="filter-office"><option value="">Semua</option><option>Grobogan</option></select></label>
     <label>Status pegawai<select id="filter-status"><option value="">Semua</option><option>Aktif</option><option>Non Aktif</option><option>Berhenti</option></select></label>
     <label>Privilege<select id="filter-privilege"><option value="">Semua</option><option>User</option><option>Admin</option></select></label>
    </div>
   </div>
   <div class="employee-toolbar">
    <button class="action-button primary" id="add-employee">＋ Tambah Pegawai</button>
    <button class="action-button" id="edit-employee">✎ Ubah</button>
    <button class="action-button" id="delete-employee">Hapus</button>
    <button class="action-button" id="import-employee">⇧ Import</button>
    <button class="action-button" id="export-employee">⇩ Export CSV</button>
    <button class="action-button" id="print-employee">Cetak</button>
    <input type="file" id="csv-file" accept=".csv,text/csv" hidden>
   </div>
   <div class="employee-table-wrap"><table class="employee-table"><thead><tr>
    <th><input type="checkbox" id="select-all" aria-label="Pilih semua"></th><th>PIN</th><th>Nama</th><th>Tanggal Lahir</th><th>Jabatan</th><th>Departemen</th><th>Kantor</th><th>No. Telp</th><th>Privilege</th><th>Status</th><th>Jadwal</th><th>Sidik Jari</th>
   </tr></thead><tbody id="employee-rows"></tbody></table></div>
   <div class="table-summary"><span id="employee-count"></span><span id="employee-data-state">Memuat data database...</span></div>
   <div class="employee-actions">
    <button class="action-button" id="select-list">Daftar Pilihan (<span id="selected-count">0</span>)</button>
    <button class="action-button" id="change-status">Ganti Status ▾</button>
    <button class="action-button" id="schedule-info">Jadwal Pegawai</button>
    <button class="action-button" id="unregistered">Pegawai Belum Registrasi</button>
    <button class="action-button" id="change-join-date">Ubah Tanggal Masuk</button>
   </div>
  </section>
 </section>
 <dialog id="employee-dialog" class="employee-dialog"><form id="employee-form" method="dialog">
  <div class="dialog-head"><strong id="dialog-title">Tambah Pegawai</strong><button type="button" id="close-dialog" aria-label="Tutup">×</button></div>
  <div class="detail-sections">
   <section class="detail-section"><h3>Identitas & Data Pribadi</h3><div class="form-grid">
    <label>PIN / ID Pegawai *<input name="pin" required maxlength="20"></label><label>Nama Lengkap *<input name="name" required></label>
    <label>Tanggal lahir<input name="birth" type="date"></label><label>Jenis kelamin<select name="gender"><option value="">Pilih</option><option>Laki-laki</option><option>Perempuan</option></select></label>
    <label>Agama<input name="religion"></label><label>Status pernikahan<select name="maritalStatus"><option value="">Pilih</option><option>Belum Menikah</option><option>Menikah</option><option>Cerai</option></select></label>
    <label>Pendidikan<input name="education"></label><label>Golongan darah<select name="bloodType"><option value="">Tidak diketahui</option><option>A</option><option>B</option><option>AB</option><option>O</option></select></label>
    <label>Jumlah anak<input name="children" type="number" min="0" max="30" value="0"></label>
   </div></section>
   <section class="detail-section"><h3>Informasi Kepegawaian</h3><div class="form-grid">
    <label>Jabatan<input name="position"></label><label>Departemen<input name="department"></label>
    <label>Kantor<input name="office"></label><label>Jadwal kerja<input name="schedule" value="Reguler"></label>
    <label>Tanggal masuk<input name="joinDate" type="date"></label><label>Akhir kontrak<input name="contractEnd" type="date"></label>
    <label>Status pegawai<select name="status"><option>Aktif</option><option>Non Aktif</option><option>Berhenti</option></select></label><label>Privilege mesin<select name="privilege"><option>User</option><option>Admin</option></select></label>
   </div></section>
   <section class="detail-section"><h3>Kontak & Alamat</h3><div class="form-grid">
    <label>No. telepon<input name="phone" type="tel"></label><label>Email<input name="email" type="email"></label>
    <label class="full-field">Alamat<textarea name="address" rows="2"></textarea></label>
   </div></section>
   <section class="detail-section"><h3>Identitas Mesin & Biometrik</h3><div class="form-grid">
    <label>Password mesin<input name="password" type="text" autocomplete="off"></label><label>RFID / Kartu<input name="rfid"></label>
    <label>Jumlah sidik jari terdaftar<input name="fingerprints" type="number" min="0" max="10" value="0"></label>
   </div><p class="form-hint">Template biometrik tidak disimpan melalui form ini. Registrasi sidik jari dilakukan melalui perangkat yang kompatibel.</p></section>
  </div>
  <p class="form-hint">PIN harus unik. Data ini masih berupa contoh lokal di memori browser, belum disimpan ke database.</p>
  <div class="dialog-actions"><button type="button" class="action-button" id="cancel-dialog">Batal</button><button type="submit" class="action-button primary">Simpan</button></div>
 </form></dialog>`;
}
function initEmployeePage(){
 const root=document.querySelector(".employee-page"); if(!root)return;
 const $=id=>document.getElementById(id);
 const fields=["pin","name","schedule","position","department","office","status","privilege"];
 let editingPin=null;
 function filtered(){
  const values={pin:$("filter-pin").value.toLowerCase(),name:$("filter-name").value.toLowerCase(),schedule:$("filter-schedule").value,position:$("filter-position").value,department:$("filter-department").value,office:$("filter-office").value,status:$("filter-status").value,privilege:$("filter-privilege").value};
  return employees.filter(e=>{
   const soon=category==="Segera habis kontrak"&&e.contractEnd&&new Date(e.contractEnd)>=new Date()&&new Date(e.contractEnd)<=new Date(Date.now()+30*86400000);
   const categoryMatch=category==="Detail pegawai"||category==="Semua"||(category==="Segera habis kontrak"?soon:e.status===category);
   return categoryMatch&&fields.every(k=>!values[k]||String(e[k]||"").toLowerCase().includes(values[k]));
  });
 }
 function render(){
  const rows=filtered();
  $("employee-rows").innerHTML=rows.map(e=>`<tr data-pin="${esc(e.pin)}" class="${selectedPins.has(e.pin)?"selected-row":""}"><td><input type="checkbox" class="row-check" data-pin="${esc(e.pin)}" ${selectedPins.has(e.pin)?"checked":""}></td><td>${esc(e.pin)}</td><td><button class="name-link" data-open="${esc(e.pin)}">${esc(e.name)}</button></td><td>${esc(e.birth||"—")}</td><td>${esc(e.position)}</td><td>${esc(e.department)}</td><td>${esc(e.office)}</td><td>${esc(e.phone)}</td><td>${esc(e.privilege)}</td><td><span class="status-pill ${e.status==="Aktif"?"is-active":""}">${esc(e.status)}</span></td><td>${esc(e.schedule)}</td><td>${Number(e.fingerprints)||0}</td></tr>`).join("")||'<tr><td colspan="12" class="no-data">Tidak ada pegawai yang cocok dengan filter.</td></tr>';
  $("employee-count").textContent=`${rows.length} dari ${employees.length} pegawai`;
  $("selected-count").textContent=selectedPins.size;
  $("select-all").checked=rows.length>0&&rows.every(e=>selectedPins.has(e.pin));
 }
 function openForm(employee){
  editingPin=employee?.pin||null; $("dialog-title").textContent=employee?"Ubah Pegawai":"Tambah Pegawai";
  const form=$("employee-form");form.reset();
  for(const [key,value] of Object.entries(employee||{})){if(form.elements[key])form.elements[key].value=value??"";}
  if(!employee){form.elements.fingerprints.value=0;form.elements.schedule.value="Reguler";}
  $("employee-dialog").showModal();
 }
 function selected(){return employees.filter(e=>selectedPins.has(e.pin));}
 root.addEventListener("input",render);root.addEventListener("change",e=>{
  if(e.target.matches(".row-check")){e.target.checked?selectedPins.add(e.target.dataset.pin):selectedPins.delete(e.target.dataset.pin);render();}
  if(e.target.id==="select-all"){filtered().forEach(e=>e.target.checked?selectedPins.add(e.pin):selectedPins.delete(e.pin));render();}
 });
 root.addEventListener("click",e=>{
  const cat=e.target.closest("[data-category]");if(cat){category=cat.dataset.category;root.querySelectorAll(".category-button").forEach(b=>b.classList.toggle("selected",b===cat));render();return;}
  const open=e.target.closest("[data-open]");if(open){const emp=employees.find(x=>x.pin===open.dataset.open);openForm(emp);return;}
  const row=e.target.closest("tr[data-pin]");if(row&&!e.target.closest("button,input")){const pin=row.dataset.pin;selectedPins.has(pin)?selectedPins.delete(pin):selectedPins.add(pin);render();}
 });
 $("add-employee").onclick=()=>openForm(null);
 $("edit-employee").onclick=()=>{const s=selected();if(s.length!==1)return alert("Pilih tepat satu pegawai untuk diubah.");openForm(s[0]);};
 $("delete-employee").onclick=async()=>{const s=selected();if(!s.length)return alert("Pilih pegawai terlebih dahulu.");if(!confirm(`Nonaktifkan ${s.length} pegawai terpilih? Riwayat absensi tetap dipertahankan.`))return;try{for(const emp of s){const id=employeeIds.get(String(emp.pin));await CfApi.request('/employees/'+id,{method:'DELETE'});}selectedPins.clear();await loadEmployeesFromApi();render();}catch(error){alert(error.message);}};
 $("employee-form").onsubmit=async e=>{e.preventDefault();const form=e.target,data=Object.fromEntries(new FormData(form));data.fingerprints=Number(data.fingerprints)||0;const duplicate=employees.some(x=>x.pin===data.pin&&x.pin!==editingPin);if(duplicate)return alert("PIN sudah digunakan.");const payload={pin:data.pin,full_name:data.name,employee_status:employeeStatusToDb(data.status),department:data.department||null,position_name:data.position||null,join_date:data.joinDate||null,contract_end:data.contractEnd||null,phone:data.phone||null,email:data.email||null,gender:data.gender==="Laki-laki"?"male":data.gender==="Perempuan"?"female":null,birth_date:data.birth||null,religion:data.religion||null,marital_status:data.maritalStatus||null,education:data.education||null,blood_type:data.bloodType||null,address:data.address||null,machine_privilege:data.privilege||null,rfid:data.rfid||null,fingerprint_count:data.fingerprints,notes:null};const btn=form.querySelector('button[type="submit"]');btn.disabled=true;try{if(editingPin){const id=employeeIds.get(String(editingPin));await CfApi.request('/employees/'+id,{method:'PUT',body:JSON.stringify(payload)});}else{await CfApi.request('/employees',{method:'POST',body:JSON.stringify(payload)});}await loadEmployeesFromApi();selectedPins.clear();$("employee-dialog").close();render();}catch(error){alert(error.message);}finally{btn.disabled=false;}};
 const close=()=>$("employee-dialog").close();$("close-dialog").onclick=close;$("cancel-dialog").onclick=close;
 $("clear-filters").onclick=()=>{root.querySelectorAll(".filter-grid input,.filter-grid select").forEach(x=>x.value="");category="Semua";root.querySelectorAll(".category-button").forEach(x=>x.classList.toggle("selected",x.dataset.category==="Detail pegawai"));render();};
 $("select-list").onclick=()=>alert(selected().map(x=>x.pin+" — "+x.name).join("\n")||"Belum ada pegawai dipilih.");
 $("change-status").onclick=async()=>{const s=selected();if(!s.length)return alert("Pilih pegawai terlebih dahulu.");const status=prompt("Masukkan status: Aktif, Non Aktif, atau Berhenti","Non Aktif");if(!["Aktif","Non Aktif","Berhenti"].includes(status))return;try{for(const emp of s){const id=employeeIds.get(String(emp.pin));await CfApi.request('/employees/'+id,{method:'PUT',body:JSON.stringify({employee_status:employeeStatusToDb(status)})});}selectedPins.clear();await loadEmployeesFromApi();render();}catch(error){alert(error.message);}};
 $("schedule-info").onclick=()=>alert(selected().map(x=>x.name+": "+x.schedule).join("\n")||"Pilih pegawai untuk melihat jadwal.");
 $("unregistered").onclick=()=>{category="Semua";root.querySelectorAll(".filter-grid input,.filter-grid select").forEach(x=>x.value="");$("employee-rows").innerHTML=employees.filter(x=>!x.fingerprints).map(e=>`<tr><td></td><td>${esc(e.pin)}</td><td>${esc(e.name)}</td><td colspan="9">Belum terdaftar sidik jari</td></tr>`).join("")||'<tr><td colspan="12">Semua pegawai contoh memiliki data sidik jari.</td></tr>';};
 $("change-join-date").onclick=()=>alert("Tanggal masuk kerja akan tersedia pada form detail pegawai tahap berikutnya.");
 $("print-employee").onclick=()=>window.print();
 $("export-employee").onclick=()=>{const cols=["pin","name","birth","position","department","office","phone","privilege","status","schedule","fingerprints"];const csv=[cols.join(","),...filtered().map(e=>cols.map(k=>'"'+String(e[k]??"").replace(/"/g,'""')+'"').join(","))].join("\r\n");const a=document.createElement("a");a.href=URL.createObjectURL(new Blob(["\ufeff"+csv],{type:"text/csv;charset=utf-8"}));a.download="clonefringer-pegawai.csv";a.click();URL.revokeObjectURL(a.href);};
 $("import-employee").onclick=()=>$("csv-file").click();
 $("csv-file").onchange=async e=>{const file=e.target.files[0];if(!file)return;const text=await file.text();const lines=text.replace(/^\ufeff/,"").split(/\r?\n/).filter(Boolean);const parse=line=>{const out=[];let value="",quoted=false;for(let i=0;i<line.length;i++){const c=line[i];if(c==='"'&&line[i+1]==='"'){value+='"';i++;}else if(c==='"')quoted=!quoted;else if(c===","&&!quoted){out.push(value);value="";}else value+=c;}out.push(value);return out;};const headers=parse(lines.shift()).map(x=>x.trim());const imported=lines.map(line=>{const vals=parse(line);return Object.fromEntries(headers.map((h,i)=>[h,vals[i]||""]));}).filter(x=>x.pin&&x.name);const existing=new Set(employees.map(x=>x.pin));const valid=imported.filter(x=>!existing.has(x.pin));if(confirm(`Ditemukan ${imported.length} baris, ${valid.length} PIN baru. Tambahkan data valid?`)){employees.push(...valid.map(x=>({...x,fingerprints:Number(x.fingerprints)||0})));render();}e.target.value="";};
 $("employee-data-state").textContent="Terhubung ke database";
 loadEmployeesFromApi().then(render).catch(error=>{ $("employee-data-state").textContent="Gagal memuat database"; $("employee-rows").innerHTML='<tr><td colspan="12" class="no-data">'+esc(error.message)+'</td></tr>'; });
 render();
}
