function settingsPage(){return `
<section class="settings-page">
 <header class="page-heading"><h1>Pengaturan Pengguna</h1><p>Kelola akun, hak akses, dan status pengguna CLONEFRINGER.</p></header>
 <div class="settings-toolbar"><span id="users-count">Memuat pengguna...</span><button class="action-button primary" id="user-add">＋ Tambah pengguna</button><button class="action-button" id="users-refresh">Muat ulang</button></div>
 <div class="settings-table-wrap"><table class="settings-table"><thead><tr><th>Username</th><th>Nama tampilan</th><th>Peran</th><th>Status</th><th>Login terakhir</th><th>Aksi</th></tr></thead><tbody id="users-body"><tr><td colspan="6" class="no-data">Memuat data...</td></tr></tbody></table></div>
 <p class="settings-note">Admin dapat mengelola semua akun. Operator mengelola operasional absensi, sedangkan Viewer hanya melihat data. Password tidak pernah ditampilkan.</p>
</section>
<dialog class="employee-dialog" id="user-dialog"><form id="user-form">
 <div class="dialog-head"><strong id="user-dialog-title">Tambah pengguna</strong><button type="button" id="user-close" aria-label="Tutup">×</button></div>
 <input type="hidden" name="id">
 <div class="form-grid">
  <label>Username<input name="username" required maxlength="80" autocomplete="off"></label>
  <label>Nama tampilan<input name="display_name" required maxlength="150"></label>
  <label>Peran<select name="role"><option value="viewer">Viewer</option><option value="operator">Operator</option><option value="admin">Admin</option></select></label>
  <label>Status<select name="is_active"><option value="1">Aktif</option><option value="0">Nonaktif</option></select></label>
  <label class="full-field">Password <span id="user-password-hint" class="form-hint">Minimal 10 karakter.</span><input name="password" type="password" minlength="10" autocomplete="new-password"></label>
 </div>
 <div class="form-hint" id="user-form-message" role="status"></div>
 <div class="dialog-actions"><button type="button" class="action-button" id="user-cancel">Batal</button><button type="submit" class="action-button primary">Simpan</button></div>
</form></dialog>`;}
function initSettingsPage(){
 const body=document.querySelector('#users-body'),count=document.querySelector('#users-count'),dialog=document.querySelector('#user-dialog'),form=document.querySelector('#user-form');
 let rows=[];
 const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const fmt=v=>v?new Date(String(v).replace(' ','T')).toLocaleString('id-ID'):'Belum pernah';
 async function load(){
  body.innerHTML='<tr><td colspan="6" class="no-data">Memuat data pengguna...</td></tr>';
  try{
   const r=await CfApi.request('/users');rows=r.data||[];count.textContent=rows.length+' akun terdaftar';
   body.innerHTML=rows.length?rows.map(u=>`<tr><td><strong>${esc(u.username)}</strong>${Number(u.id)===Number(CfApi.user?.id)?' <small>(Anda)</small>':''}</td><td>${esc(u.display_name)}</td><td>${esc(({admin:'Admin',operator:'Operator',viewer:'Viewer'})[u.role]||u.role)}</td><td><span class="status-pill ${Number(u.is_active)?'is-active':''}">${Number(u.is_active)?'Aktif':'Nonaktif'}</span></td><td>${esc(fmt(u.last_login_at))}</td><td><button class="table-action" data-edit="${Number(u.id)}">Ubah</button></td></tr>`).join(''):'<tr><td colspan="6" class="no-data">Belum ada pengguna.</td></tr>';
   body.querySelectorAll('[data-edit]').forEach(b=>b.addEventListener('click',()=>edit(Number(b.dataset.edit))));
  }catch(e){count.textContent='Gagal memuat';body.innerHTML='<tr><td colspan="6" class="no-data">'+esc(e.message)+'</td></tr>';}
 }
 function openNew(){
  form.reset();form.elements.id.value='';form.elements.username.disabled=false;form.elements.is_active.value='1';
  document.querySelector('#user-dialog-title').textContent='Tambah pengguna';
  document.querySelector('#user-password-hint').textContent='Wajib diisi, minimal 10 karakter.';
  form.elements.password.required=true;document.querySelector('#user-form-message').textContent='';
  dialog.showModal();
 }
 function edit(id){
  const u=rows.find(x=>Number(x.id)===id);if(!u)return;
  form.reset();form.elements.id.value=u.id;form.elements.username.value=u.username;form.elements.username.disabled=true;
  form.elements.display_name.value=u.display_name;form.elements.role.value=u.role;form.elements.is_active.value=String(Number(u.is_active));
  form.elements.password.value='';form.elements.password.required=false;
  document.querySelector('#user-dialog-title').textContent='Ubah pengguna';
  document.querySelector('#user-password-hint').textContent='Kosongkan jika tidak ingin mengganti password. Jika diisi, minimal 10 karakter.';
  document.querySelector('#user-form-message').textContent='';
  if(Number(u.id)===Number(CfApi.user?.id)){form.elements.role.disabled=true;form.elements.is_active.disabled=true;}else{form.elements.role.disabled=false;form.elements.is_active.disabled=false;}
  dialog.showModal();
 }
 document.querySelector('#user-add').addEventListener('click',openNew);
 document.querySelector('#users-refresh').addEventListener('click',load);
 document.querySelector('#user-close').addEventListener('click',()=>dialog.close());
 document.querySelector('#user-cancel').addEventListener('click',()=>dialog.close());
 form.addEventListener('submit',async e=>{
  e.preventDefault();const submit=form.querySelector('[type=submit]');submit.disabled=true;
  const id=form.elements.id.value;
  const payload={display_name:form.elements.display_name.value.trim(),role:form.elements.role.value,is_active:form.elements.is_active.value==='1',password:form.elements.password.value};
  if(!id){payload.username=form.elements.username.value.trim();}
  try{
   await CfApi.request(id?'/users/'+encodeURIComponent(id):'/users',{method:id?'PUT':'POST',body:JSON.stringify(payload)});
   dialog.close();await load();alert(id?'Data pengguna diperbarui.':'Pengguna baru berhasil dibuat.');
  }catch(err){document.querySelector('#user-form-message').textContent=err.message;}
  finally{submit.disabled=false;}
 });
 load();
}
