const pages = {
 dashboard:{title:"Dashboard",description:"Ringkasan sistem dan alur operasional."},
 pegawai:{title:"Pegawai",description:"Pengelolaan biodata, status, PIN, jadwal, dan identitas pegawai."},
 pengecualian:{title:"Pengecualian",description:"Kelola izin, cuti, lembur, dan perubahan jadwal."},
 mesin:{title:"Mesin",description:"Daftar perangkat fingerprint dan aktivitas sinkronisasi."},
 laporan:{title:"Laporan",description:"Laporan scanlog, rincian harian, rekap periode, dan kehadiran."},
 pengaturan:{title:"Pengaturan",description:"Konfigurasi jam kerja, jadwal, pengguna, kalender, dan sistem."}
};
const content=document.querySelector("#page-content");
const navButtons=[...document.querySelectorAll(".nav-item")];
const dashboardMarkup=content.innerHTML;
function activate(page){
 navButtons.forEach(button=>{const active=button.dataset.page===page;button.classList.toggle("active",active);button.setAttribute("aria-current",active?"page":"false");});
 if(page==="dashboard"){content.innerHTML=dashboardMarkup;return;}
 const item=pages[page]||pages.dashboard;
 content.innerHTML=`<section class="placeholder-page"><header class="page-heading"><h1>${item.title}</h1><p>${item.description}</p></header><div class="toolbar"><button class="primary" disabled>＋ Tambah (tahap berikutnya)</button><button disabled>Import</button><button disabled>Export</button><button disabled>Cetak</button></div><div class="empty-state"><strong>Modul ${item.title} belum diimplementasikan.</strong><p>Kerangka navigasi sudah tersedia. Fungsi dan koneksi database akan dibangun pada tahap modul berikutnya.</p></div></section>`;
}
navButtons.forEach(button=>button.addEventListener("click",()=>activate(button.dataset.page)));
document.querySelectorAll(".rail-tab").forEach(button=>button.addEventListener("click",()=>document.querySelectorAll(".rail-tab").forEach(tab=>tab.classList.toggle("selected",tab===button))));
