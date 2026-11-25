const pages={dashboard:{title:"Dashboard"},pegawai:{title:"Pegawai"},pengecualian:{title:"Pengecualian",description:"Kelola izin, cuti, lembur, dan perubahan jadwal."},mesin:{title:"Mesin",description:"Daftar perangkat fingerprint dan aktivitas sinkronisasi."},laporan:{title:"Laporan",description:"Laporan scanlog, rincian harian, rekap periode, dan kehadiran."},pengaturan:{title:"Pengaturan",description:"Konfigurasi jam kerja, jadwal, pengguna, kalender, dan sistem."}};
const content=document.querySelector("#page-content"),navButtons=[...document.querySelectorAll(".nav-item")],dashboardMarkup=content.innerHTML;
function activate(page){navButtons.forEach(b=>{const active=b.dataset.page===page;b.classList.toggle("active",active);b.setAttribute("aria-current",active?"page":"false");});
 if(page==="dashboard"){content.innerHTML=dashboardMarkup;return;}
 if(page==="pegawai"){content.innerHTML=employeePage();initEmployeePage();return;}
 if(page==="pengecualian"){content.innerHTML=exceptionPage();initExceptionPage();return;}
 if(page==="mesin"){content.innerHTML=machinePage();initMachinePage();return;}
 if(page==="laporan"){content.innerHTML=reportPage();initReportPage();return;}
 const item=pages[page]||pages.dashboard;content.innerHTML=`<section class="placeholder-page"><header class="page-heading"><h1>${item.title}</h1><p>${item.description||"Modul sedang disiapkan."}</p></header><div class="toolbar"><button class="primary" disabled>＋ Tambah (tahap berikutnya)</button><button disabled>Import</button><button disabled>Export</button><button disabled>Cetak</button></div><div class="empty-state"><strong>Modul ${item.title} belum diimplementasikan.</strong><p>Kerangka navigasi tersedia. Fungsi dan koneksi database dibangun pada tahap modul berikutnya.</p></div></section>`;
}
navButtons.forEach(b=>b.addEventListener("click",()=>activate(b.dataset.page)));
document.querySelectorAll(".rail-tab").forEach(b=>b.addEventListener("click",()=>document.querySelectorAll(".rail-tab").forEach(t=>t.classList.toggle("selected",t===b))));
