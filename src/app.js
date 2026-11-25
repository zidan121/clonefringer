const pages={dashboard:{title:"Dashboard"},pegawai:{title:"Pegawai"},pengecualian:{title:"Pengecualian",description:"Kelola izin, cuti, lembur, dan perubahan jadwal."},mesin:{title:"Mesin",description:"Daftar perangkat fingerprint dan aktivitas sinkronisasi."},laporan:{title:"Laporan",description:"Laporan scanlog, rincian harian, rekap periode, dan kehadiran."},pengaturan:{title:"Pengaturan",description:"Konfigurasi jam kerja, jadwal, pengguna, kalender, dan sistem."}};
const content=document.querySelector("#page-content"),navButtons=[...document.querySelectorAll(".nav-item")],dashboardMarkup=content.innerHTML;
function showLogin(message="") {
 let layer=document.querySelector("#login-layer");
 if(!layer){layer=document.createElement("div");layer.id="login-layer";layer.className="login-layer";layer.innerHTML=`<form id="login-form" class="login-card"><div class="login-brand"><span class="brand-mark">C</span><div><strong>CLONEFRINGER</strong><small>PERSONNEL MANAGEMENT</small></div></div><h1>Masuk ke aplikasi</h1><p>Gunakan akun administrator atau operator yang telah dibuat di database.</p><label>Username<input name="username" autocomplete="username" required></label><label>Password<input name="password" type="password" autocomplete="current-password" required></label><div class="login-error" id="login-error"></div><button class="primary" type="submit">Masuk</button><small class="login-foot">Koneksi API: <span id="api-address"></span></small></form>`;document.body.append(layer);
  layer.querySelector("#login-form").addEventListener("submit",async e=>{e.preventDefault();const form=e.currentTarget,btn=form.querySelector("button[type=submit]"),err=layer.querySelector("#login-error");btn.disabled=true;btn.textContent="Memeriksa...";err.textContent="";try{const fd=new FormData(form);await CfApi.login(fd.get("username"),fd.get("password"));layer.remove();afterLogin();}catch(error){err.textContent=error.message;}finally{btn.disabled=false;btn.textContent="Masuk";}});
 }
 layer.querySelector("#login-error").textContent=message;
 layer.querySelector("#api-address").textContent=window.CLONEFRINGER_API_BASE||"/api";
}
function activate(page){
 navButtons.forEach(b=>{const active=b.dataset.page===page;b.classList.toggle("active",active);b.setAttribute("aria-current",active?"page":"false");});
 if(page==="dashboard"){content.innerHTML=dashboardMarkup;return;}
 if(page==="pegawai"){content.innerHTML=employeePage();initEmployeePage();return;}
 if(page==="pengecualian"){content.innerHTML=exceptionPage();initExceptionPage();return;}
 if(page==="mesin"){content.innerHTML=machinePage();initMachinePage();return;}
 if(page==="laporan"){content.innerHTML=reportPage();initReportPage();return;}
 if(page==="jadwal"){content.innerHTML=schedulePage();initSchedulePage();return;}
 if(page==="kalender"){content.innerHTML=calendarPage();initCalendarPage();return;}
 const item=pages[page]||pages.dashboard;content.innerHTML=`<section class="placeholder-page"><header class="page-heading"><h1>${item.title}</h1><p>${item.description||"Modul sedang disiapkan."}</p></header><div class="empty-state"><strong>Modul ${item.title} belum tersedia.</strong><p>Bagian ini akan diaktifkan setelah API dan alur datanya selesai.</p></div></section>`;
}
function afterLogin(){
 navButtons.forEach(b=>b.addEventListener("click",()=>activate(b.dataset.page)));
 document.querySelectorAll(".rail-tab").forEach(b=>b.addEventListener("click",()=>document.querySelectorAll(".rail-tab").forEach(t=>t.classList.toggle("selected",t===b))));
 const status=document.querySelector(".status-user");if(status)status.innerHTML=`<i></i> ${CfApi.user?.display_name||CfApi.user?.username||"pengguna"} <button id="logout-button" class="logout-button">Keluar</button>`;
 const db=document.querySelector(".statusbar span:nth-child(3)");if(db)db.innerHTML='DB: <b>API aktif</b>';
 const state=document.querySelector(".statusbar .status-spacer");if(state&&state.nextElementSibling)state.nextElementSibling.innerHTML='Status: <b>Sesi aktif</b>';
 document.querySelector("#logout-button")?.addEventListener("click",async()=>{if(!confirm("Keluar dari CLONEFRINGER?"))return;try{await CfApi.logout();location.reload();}catch(e){alert(e.message);}});
 activate("dashboard");
}
(async()=>{
 try{await CfApi.me();afterLogin();}
 catch(error){showLogin(error.message.includes("Belum login")?"":"API belum terhubung: "+error.message);}
})();
