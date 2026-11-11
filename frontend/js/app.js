// ============================================
// Smart Attendance – Simulasi Frontend Enterprise
// Professional, Clean Look & Corporate Luxury
// ============================================

const API_BASE = '../api';

// ---- State ----
let currentUser             = null;
let currentLat              = null;
let currentLng              = null;
let fotoBase64              = null;
let map                     = null;
let markerUser              = null;
let markerKampus            = null;
let circleRadius            = null;
let jamInterval             = null;
let kameraStream            = null;
let currentAttendanceStage  = 'masuk'; // 'masuk' | 'pulang' | 'selesai' | 'izin'
let selectedKategoriIzin    = 'sakit'; // 'sakit' | 'izin' | 'dispensasi'
let modalConfirmCallback    = null;
let modalCancelCallback     = null;

// Koordinat kampus – diisi dari API settings
let KAMPUS_LAT = -6.2088;
let KAMPUS_LNG = 106.8456;
let MAX_RADIUS = 100;

// ============================================
// INIT
// ============================================
document.addEventListener('DOMContentLoaded', async () => {
  // Ambil settings koordinat kampus dari server
  try {
    const res = await fetchJSON(`${API_BASE}/settings.php`);
    if (res.status === 'success' && res.data) {
      KAMPUS_LAT = parseFloat(res.data.kampus_lat?.value ?? KAMPUS_LAT);
      KAMPUS_LNG = parseFloat(res.data.kampus_lng?.value ?? KAMPUS_LNG);
      MAX_RADIUS = parseInt(res.data.max_radius?.value   ?? MAX_RADIUS);
    }
  } catch (_) { /* gunakan nilai default */ }

  const saved = sessionStorage.getItem('absensi_user');
  if (saved) {
    currentUser = JSON.parse(saved);
    showHome();
  } else {
    showPage('page-login');
    resetDemoAccountPills();
  }

  updateJam();
  jamInterval = setInterval(updateJam, 1000);
});

// ============================================
// NOTIFIKASI TOAST & MODAL POPUP SYSTEM
// ============================================
function showToast(message, type = 'info', duration = 3500) {
  const container = document.getElementById('toast-container');
  if (!container) return;

  const icons = {
    success: 'fa-solid fa-circle-check',
    error:   'fa-solid fa-circle-exclamation',
    warning: 'fa-solid fa-triangle-exclamation',
    info:    'fa-solid fa-circle-info'
  };

  const toast = document.createElement('div');
  toast.className = `toast-item toast-${type}`;
  toast.innerHTML = `
    <i class="${icons[type] || icons.info}"></i>
    <span style="flex:1;">${escapeHtml(message)}</span>
    <button type="button" style="background:none;border:none;color:inherit;opacity:0.6;cursor:pointer;padding:0 2px;font-size:12px;" onclick="this.parentElement.remove()">
      <i class="fa-solid fa-xmark"></i>
    </button>
  `;

  container.appendChild(toast);

  setTimeout(() => {
    toast.style.animation = 'fadeOut 0.3s forwards';
    setTimeout(() => toast.remove(), 300);
  }, duration);
}

function showModalPopup(options) {
  const {
    title = 'NOTIFIKASI SISTEM',
    subtitle = '',
    type = 'info', // 'success' | 'error' | 'confirm' | 'info'
    detailsHtml = '',
    confirmText = 'Mengerti',
    cancelText = null,
    onConfirm = null,
    onCancel = null
  } = options;

  const backdrop = document.getElementById('modal-popup-backdrop');
  const iconWrap = document.getElementById('modal-popup-icon');
  const iconFa   = document.getElementById('modal-icon-fa');
  const titleEl  = document.getElementById('modal-popup-title');
  const subEl    = document.getElementById('modal-popup-subtitle');
  const bodyEl   = document.getElementById('modal-popup-body');
  const actWrap  = document.getElementById('modal-actions-wrap');

  if (!backdrop) return;

  // Set type styling
  iconWrap.className = 'modal-icon-circle ' + type;
  if (type === 'success') {
    iconFa.className = 'fa-solid fa-circle-check';
  } else if (type === 'error') {
    iconFa.className = 'fa-solid fa-circle-xmark';
  } else if (type === 'confirm') {
    iconFa.className = 'fa-solid fa-circle-question';
  } else {
    iconFa.className = 'fa-solid fa-circle-info';
  }

  titleEl.textContent = title;
  subEl.textContent   = subtitle;

  if (detailsHtml) {
    bodyEl.innerHTML = detailsHtml;
    bodyEl.style.display = 'block';
  } else {
    bodyEl.innerHTML = '';
    bodyEl.style.display = 'none';
  }

  // Button actions
  modalConfirmCallback = onConfirm;
  modalCancelCallback  = onCancel;

  if (cancelText) {
    actWrap.innerHTML = `
      <button type="button" class="btn btn-secondary btn-full" onclick="handleModalCancel()">${escapeHtml(cancelText)}</button>
      <button type="button" class="btn btn-primary btn-full" onclick="handleModalConfirm()">${escapeHtml(confirmText)}</button>
    `;
  } else {
    actWrap.innerHTML = `
      <button type="button" class="btn btn-primary btn-full" onclick="handleModalConfirm()">${escapeHtml(confirmText)}</button>
    `;
  }

  backdrop.style.display = 'flex';
}

function closeModalPopup() {
  const backdrop = document.getElementById('modal-popup-backdrop');
  if (backdrop) backdrop.style.display = 'none';
  modalConfirmCallback = null;
  modalCancelCallback  = null;
}

function handleModalConfirm() {
  const cb = modalConfirmCallback;
  closeModalPopup();
  if (typeof cb === 'function') cb();
}

function handleModalCancel() {
  const cb = modalCancelCallback;
  closeModalPopup();
  if (typeof cb === 'function') cb();
}

// ============================================
// NAVIGASI
// ============================================
function showPage(id) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.getElementById(id).classList.add('active');
}

function showHome() {
  showPage('page-home');
  document.getElementById('user-name').textContent = currentUser.nama;
  document.getElementById('user-nim').textContent  = 'NIM: ' + currentUser.nim;
  switchTab('absensi');
  initMap();
  setTimeout(() => { if (map) map.invalidateSize(); }, 250);
  refreshLokasi();
  checkStatusHariIni();
}

function switchTab(tab) {
  document.querySelectorAll('.tab-btn').forEach((b, i) => {
    b.classList.toggle('active',
      (i === 0 && tab === 'absensi') || (i === 1 && tab === 'riwayat')
    );
  });
  document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
  document.getElementById('tab-' + tab).classList.add('active');
  if (tab === 'riwayat') {
    loadRiwayat();
  } else if (tab === 'absensi' && currentUser) {
    if (map) setTimeout(() => map.invalidateSize(), 150);
    checkStatusHariIni();
  }
}

// ============================================
// STATUS HARI INI & 2-STAGE ATTENDANCE WORKFLOW
// ============================================
async function checkStatusHariIni() {
  if (!currentUser) return;

  try {
    const res = await fetchJSON(`${API_BASE}/status_today.php?user_id=${currentUser.user_id}`);
    if (res.status === 'success' && res.data) {
      applyAttendanceStage(res.data);
    }
  } catch (e) {
    console.warn('Gagal cek status harian:', e);
  }
}

function applyAttendanceStage(data) {
  const badge       = document.getElementById('absensi-status-badge');
  const pillMasuk   = document.getElementById('step-pill-masuk');
  const pillPulang  = document.getElementById('step-pill-pulang');
  const iconMasuk   = document.getElementById('step-icon-masuk');
  const iconPulang  = document.getElementById('step-icon-pulang');
  const timeMasuk   = document.getElementById('step-time-masuk');
  const timePulang  = document.getElementById('step-time-pulang');
  const btnAbsen    = document.getElementById('btn-absen');
  const btnIzin     = document.getElementById('btn-toggle-izin');
  const panelIzin   = document.getElementById('panel-form-izin');

  const statusLower = (data.status_masuk || '').toLowerCase();
  const isIzinRecorded = data.is_izin || ['izin', 'sakit', 'dispensasi'].includes(statusLower);

  if (isIzinRecorded) {
    // ---- STATUS: IZIN TIDAK HADIR TERCATAT ----
    currentAttendanceStage = 'izin';
    const kat = statusLower.toUpperCase();
    const jamIzin = data.waktu_masuk ? data.waktu_masuk.substring(11, 16) : '';

    if (badge) {
      badge.textContent = `Izin: ${kat} (${jamIzin || 'Hari Ini'})`;
      badge.className   = 'badge badge-hadir';
    }

    let cleanCatatan = (data.catatan || '').trim();
    const lowerCat = cleanCatatan.toLowerCase();
    if (lowerCat.startsWith(statusLower + ':')) {
      cleanCatatan = cleanCatatan.substring(statusLower.length + 1).trim();
    } else if (lowerCat.startsWith(statusLower + ' ')) {
      cleanCatatan = cleanCatatan.substring(statusLower.length + 1).trim();
    }
    const labelKat = (kat === 'SAKIT') ? 'Sakit' : (kat === 'DISPENSASI') ? 'Dispensasi' : 'Izin';
    const catatanTampil = cleanCatatan ? `: ${cleanCatatan}` : '';

    if (pillMasuk)  { pillMasuk.className = 'step-pill completed'; }
    if (iconMasuk)  { iconMasuk.innerHTML = '<i class="fa-solid fa-file-signature"></i>'; }
    if (timeMasuk)  { timeMasuk.textContent = `${labelKat}${catatanTampil}`; }

    if (pillPulang) { pillPulang.className = 'step-pill disabled'; }
    if (iconPulang) { iconPulang.innerHTML = '<i class="fa-solid fa-minus"></i>'; }
    if (timePulang) { timePulang.textContent = 'Tidak berlaku (Izin)'; }

    if (btnAbsen) {
      btnAbsen.disabled = true;
      btnAbsen.className = 'status-complete-pill';
      btnAbsen.innerHTML = `<i class="fa-solid fa-circle-check" style="color:#16a34a;font-size:13px;"></i> <span id="btn-absen-text">Izin Hari Ini Tercatat (${labelKat})</span>`;
    }

    if (btnIzin) {
      btnIzin.style.display = 'none';
    }
    if (panelIzin) panelIzin.style.display = 'none';

  } else if (!data.has_masuk) {
    // ---- TAHAP 1: PRESENSI MASUK ----
    currentAttendanceStage = 'masuk';

    if (badge) {
      badge.textContent = 'Belum Presensi';
      badge.className   = 'badge badge-idle';
    }

    if (pillMasuk)  { pillMasuk.className = 'step-pill active'; }
    if (iconMasuk)  { iconMasuk.textContent = '1'; }
    if (timeMasuk)  { timeMasuk.textContent = 'Belum terekam'; }

    if (pillPulang) { pillPulang.className = 'step-pill pending'; }
    if (iconPulang) { iconPulang.textContent = '2'; }
    if (timePulang) { timePulang.textContent = 'Menunggu masuk'; }

    if (btnAbsen) {
      btnAbsen.disabled = false;
      btnAbsen.className = 'btn btn-primary btn-full btn-absen';
      btnAbsen.innerHTML = '<i class="fa-solid fa-arrow-right-to-bracket" id="btn-absen-icon"></i> <span id="btn-absen-text">Konfirmasi Presensi Masuk</span>';
    }

    if (btnIzin) {
      btnIzin.style.display = 'inline-flex';
      btnIzin.disabled = false;
      btnIzin.className = 'btn btn-secondary btn-full btn-izin';
      btnIzin.innerHTML = '<i class="fa-regular fa-calendar-minus" style="color:#a16207;font-size:12.5px;"></i> <span id="btn-izin-label">Izin Tidak Hadir</span>';
    }

  } else if (!data.has_pulang) {
    // ---- TAHAP 2: PRESENSI PULANG ----
    currentAttendanceStage = 'pulang';

    const isHadir = (data.status_masuk === 'hadir');
    const jamMsk  = data.waktu_masuk ? data.waktu_masuk.substring(11, 16) : '';

    if (badge) {
      badge.textContent = `Masuk: ${data.status_masuk.toUpperCase()} (${jamMsk})`;
      badge.className   = 'badge ' + (isHadir ? 'badge-hadir' : 'badge-terlambat');
    }

    if (pillMasuk)  { pillMasuk.className = 'step-pill completed'; }
    if (iconMasuk)  { iconMasuk.innerHTML = '<i class="fa-solid fa-check"></i>'; }
    if (timeMasuk)  { timeMasuk.textContent = `${jamMsk} WIB (${data.status_masuk.toUpperCase()})`; }

    if (pillPulang) { pillPulang.className = 'step-pill active'; }
    if (iconPulang) { iconPulang.textContent = '2'; }
    if (timePulang) { timePulang.textContent = 'Siap direkam'; }

    if (btnAbsen) {
      btnAbsen.disabled = false;
      btnAbsen.className = 'btn btn-primary btn-full btn-absen';
      btnAbsen.innerHTML = '<i class="fa-solid fa-arrow-right-from-bracket" id="btn-absen-icon"></i> <span id="btn-absen-text">Konfirmasi Presensi Pulang</span>';
    }

    if (btnIzin) {
      btnIzin.style.display = 'none';
    }
    if (panelIzin) panelIzin.style.display = 'none';

  } else {
    // ---- TAHAP 3: SELESAI SEMUA ----
    currentAttendanceStage = 'selesai';

    const jamMsk = data.waktu_masuk  ? data.waktu_masuk.substring(11, 16) : '';
    const jamPlg = data.waktu_pulang ? data.waktu_pulang.substring(11, 16) : '';

    if (badge) {
      badge.textContent = `Selesai: ${jamMsk} • ${jamPlg}`;
      badge.className   = 'badge badge-hadir';
    }

    if (pillMasuk)  { pillMasuk.className = 'step-pill completed'; }
    if (iconMasuk)  { iconMasuk.innerHTML = '<i class="fa-solid fa-check"></i>'; }
    if (timeMasuk)  { timeMasuk.textContent = `${jamMsk} WIB (HADIR)`; }

    if (pillPulang) { pillPulang.className = 'step-pill completed'; }
    if (iconPulang) { iconPulang.innerHTML = '<i class="fa-solid fa-check"></i>'; }
    if (timePulang) { timePulang.textContent = `${jamPlg} WIB (PULANG)`; }

    if (btnAbsen) {
      btnAbsen.disabled = true;
      btnAbsen.className = 'status-complete-pill';
      btnAbsen.innerHTML = '<i class="fa-solid fa-circle-check" style="color:#16a34a;font-size:13px;"></i> <span id="btn-absen-text">Presensi Hari Ini Selesai</span>';
    }

    if (btnIzin) {
      btnIzin.style.display = 'none';
    }
    if (panelIzin) panelIzin.style.display = 'none';
  }
}

// ============================================
// JAM REAL-TIME
// ============================================
function updateJam() {
  const now = new Date();
  const tglEl = document.getElementById('tanggal-hari');
  const jamEl = document.getElementById('jam-sekarang');
  if (tglEl) {
    tglEl.textContent = now.toLocaleDateString('id-ID', {
      weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
    });
  }
  if (jamEl) {
    jamEl.textContent = now.toLocaleTimeString('id-ID');
  }
}

// ============================================
// LOGIN, REGISTER & LOGOUT
// ============================================
function togglePassword() {
  const inp = document.getElementById('password');
  inp.type = inp.type === 'password' ? 'text' : 'password';
}

function toggleRegPassword() {
  const inp = document.getElementById('reg-password');
  const icon = document.getElementById('reg-eye-icon');
  if (!inp) return;
  if (inp.type === 'password') {
    inp.type = 'text';
    if (icon) { icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
  } else {
    inp.type = 'password';
    if (icon) { icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
  }
}

function switchAuthView(mode) {
  const cardLogin = document.getElementById('card-auth-login');
  const cardRegister = document.getElementById('card-auth-register');
  const alertLogin = document.getElementById('login-alert');
  const alertReg = document.getElementById('register-alert');

  if (alertLogin) hideAlert(alertLogin);
  if (alertReg) hideAlert(alertReg);

  if (mode === 'register') {
    if (cardLogin) cardLogin.style.display = 'none';
    if (cardRegister) {
      cardRegister.style.display = 'block';
      const regNama = document.getElementById('reg-nama');
      if (regNama) regNama.focus();
    }
  } else {
    if (cardRegister) cardRegister.style.display = 'none';
    if (cardLogin) cardLogin.style.display = 'block';
  }
}

async function doRegister() {
  const nama     = (document.getElementById('reg-nama')?.value || '').trim();
  const nim      = (document.getElementById('reg-nim')?.value || '').trim();
  const password = (document.getElementById('reg-password')?.value || '').trim();
  const alertEl  = document.getElementById('register-alert');
  const btnText  = document.getElementById('register-btn-text');
  const btn      = document.getElementById('btn-submit-register');

  hideAlert(alertEl);

  if (!nama || !nim || !password) {
    showAlert(alertEl, 'Nama lengkap, NIM, dan kata sandi wajib diisi.', 'error');
    showToast('Nama lengkap, NIM, dan kata sandi wajib diisi.', 'error');
    return;
  }

  if (nim.length < 3) {
    showAlert(alertEl, 'NIM minimal terdiri dari 3 karakter.', 'error');
    return;
  }

  if (password.length < 4) {
    showAlert(alertEl, 'Kata sandi minimal terdiri dari 4 karakter.', 'error');
    return;
  }

  if (btn) btn.disabled = true;
  if (btnText) btnText.innerHTML = '<span class="spinner"></span>Mendaftarkan…';

  try {
    const res = await postJSON(`${API_BASE}/register.php`, { nama, nim, password });
    if (res.status === 'success') {
      showToast('Pendaftaran akun berhasil! Silakan masuk.', 'success', 3500);

      // Isi form login dengan data baru
      const inpNim = document.getElementById('nim');
      const inpPass = document.getElementById('password');
      if (inpNim) inpNim.value = nim;
      if (inpPass) inpPass.value = password;

      // Bersihkan form registrasi
      const rNama = document.getElementById('reg-nama');
      const rNim = document.getElementById('reg-nim');
      const rPass = document.getElementById('reg-password');
      if (rNama) rNama.value = '';
      if (rNim) rNim.value = '';
      if (rPass) rPass.value = '';

      // Alihkan ke tampilan login
      switchAuthView('login');

      const loginAlert = document.getElementById('login-alert');
      if (loginAlert) {
        showAlert(loginAlert, `Akun ${nama} (${nim}) berhasil didaftarkan. Silakan tekan tombol Masuk.`, 'success');
      }
    } else {
      showAlert(alertEl, res.message || 'Pendaftaran gagal.', 'error');
      showToast(res.message || 'Pendaftaran gagal.', 'error');
    }
  } catch (err) {
    showAlert(alertEl, 'Gagal terhubung ke server: ' + err.message, 'error');
    showToast('Gagal terhubung: ' + err.message, 'error');
  } finally {
    if (btn) btn.disabled = false;
    if (btnText) btnText.textContent = 'Daftar Akun';
  }
}

async function doLogin() {
  const nim      = document.getElementById('nim').value.trim();
  const password = document.getElementById('password').value.trim();
  const alertEl  = document.getElementById('login-alert');
  const btnText  = document.getElementById('login-btn-text');

  hideAlert(alertEl);

  if (!nim || !password) {
    showAlert(alertEl, 'NIM dan password wajib diisi', 'error');
    showToast('NIM dan password wajib diisi', 'error');
    return;
  }

  btnText.innerHTML = '<span class="spinner"></span>Memproses…';
  document.querySelector('#page-login .btn-primary').disabled = true;

  try {
    const res = await postJSON(`${API_BASE}/login.php`, { nim, password });
    if (res.status === 'success') {
      currentUser = res.data;
      sessionStorage.setItem('absensi_user', JSON.stringify(currentUser));
      showToast(`Selamat datang, ${currentUser.nama}!`, 'success');
      showHome();
    } else {
      showAlert(alertEl, res.message, 'error');
      showToast(res.message || 'Autentikasi gagal', 'error');
    }
  } catch (e) {
    showAlert(alertEl, 'Gagal terhubung ke server: ' + e.message, 'error');
    showToast('Gagal terhubung ke server: ' + e.message, 'error');
  } finally {
    btnText.textContent = 'Masuk';
    document.querySelector('#page-login .btn-primary').disabled = false;
  }
}

function doLogout() {
  showModalPopup({
    title: 'KONFIRMASI KELUAR',
    subtitle: 'Apakah Anda yakin ingin mengakhiri sesi presensi personel ini?',
    type: 'confirm',
    detailsHtml: `
      <div class="modal-row"><span class="modal-lbl">Personel:</span><span class="modal-val">${currentUser ? escapeHtml(currentUser.nama) : 'Mahasiswa'}</span></div>
      <div class="modal-row"><span class="modal-lbl">NIM:</span><span class="modal-val">${currentUser ? escapeHtml(currentUser.nim) : '-'}</span></div>
    `,
    confirmText: 'Ya, Keluar',
    cancelText: 'Batal',
    onConfirm: () => {
      sessionStorage.removeItem('absensi_user');
      currentUser = null;
      fotoBase64  = null;
      currentLat  = null;
      currentLng  = null;
      tutupKamera();
      hapusFoto();
      toggleFormIzin(false);
      const catInput = document.getElementById('catatan-izin');
      if (catInput) catInput.value = '';
      showPage('page-login');
      // Isi kembali akun demo default agar form login tidak pernah kosong
      document.getElementById('nim').value      = '2021001';
      document.getElementById('password').value = '123456';
      resetDemoAccountPills('2021001');
      const alertEl = document.getElementById('login-alert');
      if (alertEl) hideAlert(alertEl);
    }
  });
}

// ============================================
// DEMO ACCOUNT SWITCHER
// ============================================
function selectDemoAccount(nim, password, nama, btn) {
  const nimEl  = document.getElementById('nim');
  const passEl = document.getElementById('password');
  if (nimEl)  nimEl.value  = nim;
  if (passEl) passEl.value = password;

  document.querySelectorAll('.demo-pill-btn').forEach(b => {
    b.classList.remove('active');
    const icon = b.querySelector('.demo-pill-icon i');
    if (icon) icon.className = 'fa-solid fa-user';
  });

  if (btn) {
    btn.classList.add('active');
    const icon = btn.querySelector('.demo-pill-icon i');
    if (icon) icon.className = 'fa-solid fa-user-check';
  } else {
    resetDemoAccountPills(nim);
  }

  const alertEl = document.getElementById('login-alert');
  if (alertEl) hideAlert(alertEl);

  showToast(`Akun demo ${nama} (${nim}) aktif. Silakan tekan Masuk.`, 'info', 2200);
}

function resetDemoAccountPills(defaultNim = '2021001') {
  document.querySelectorAll('.demo-pill-btn').forEach(b => {
    const isDefault = (b.getAttribute('data-nim') === defaultNim);
    b.classList.toggle('active', isDefault);
    const icon = b.querySelector('.demo-pill-icon i');
    if (icon) {
      icon.className = isDefault ? 'fa-solid fa-user-check' : 'fa-solid fa-user';
    }
  });
}

// ============================================
// PETA (Leaflet)
// ============================================
function initMap() {
  if (map) return;

  const mapEl = document.getElementById('map');
  if (!mapEl) return;

  map = L.map('map').setView([KAMPUS_LAT, KAMPUS_LNG], 16);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 19
  }).addTo(map);

  markerKampus = L.marker([KAMPUS_LAT, KAMPUS_LNG], {
    icon: L.divIcon({
      className: '',
      html: '<div style="width:36px;height:36px;background:linear-gradient(135deg, #dfa86a, #c7924d);border:2.5px solid #ffffff;box-shadow:0 4px 14px rgba(199,146,77,0.45);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#141210;font-size:15px;"><i class="fa-solid fa-building-columns"></i></div>',
      iconAnchor: [18, 36]
    })
  }).addTo(map).bindPopup('<b>Titik Presensi Kampus</b>');

  circleRadius = L.circle([KAMPUS_LAT, KAMPUS_LNG], {
    radius: MAX_RADIUS,
    color: '#d4a362', fillColor: '#d4a362', fillOpacity: .16, weight: 2
  }).addTo(map);
}

function updateMapUser(lat, lng) {
  if (!map) return;
  if (markerUser) {
    markerUser.setLatLng([lat, lng]);
  } else {
    markerUser = L.marker([lat, lng], {
      icon: L.divIcon({
        className: '',
        html: '<div style="width:34px;height:34px;background:linear-gradient(135deg, #23694b, #174c35);border:2.5px solid #ffffff;box-shadow:0 4px 14px rgba(35,105,75,0.4);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#ffffff;font-size:14px;"><i class="fa-solid fa-user-check"></i></div>',
        iconAnchor: [17, 34]
      })
    }).addTo(map).bindPopup('<b>Posisi Anda</b>');
  }
  const bounds = L.latLngBounds([[lat, lng], [KAMPUS_LAT, KAMPUS_LNG]]);
  map.fitBounds(bounds, { padding: [40, 40] });
}

// ============================================
// GEOLOCATION
// ============================================
function refreshLokasi() {
  document.getElementById('koordinat-text').textContent = 'Mendeteksi…';
  const jText = document.getElementById('jarak-text');
  if (jText) jText.textContent = '–';
  document.getElementById('status-lokasi-text').textContent = '–';

  if (!navigator.geolocation) {
    const errMsg = 'Sensor GPS tidak didukung';
    document.getElementById('koordinat-text').textContent = errMsg;
    return;
  }

  navigator.geolocation.getCurrentPosition(
    pos => {
      currentLat = pos.coords.latitude;
      currentLng = pos.coords.longitude;

      const jarak       = hitungJarak(currentLat, currentLng, KAMPUS_LAT, KAMPUS_LNG);
      const dalamRadius = jarak <= MAX_RADIUS;

      document.getElementById('koordinat-text').textContent =
        currentLat.toFixed(5) + ', ' + currentLng.toFixed(5);
      if (jText) {
        jText.textContent = Math.round(jarak) + ' m (maks ' + MAX_RADIUS + ' m)';
      }
      document.getElementById('status-lokasi-text').innerHTML = dalamRadius
        ? '<span style="color:#16a34a;font-weight:600"><i class="fa-solid fa-circle-check" style="margin-right:5px;"></i>Memenuhi radius presensi (' + Math.round(jarak) + 'm)</span>'
        : '<span style="color:#dc2626;font-weight:600"><i class="fa-solid fa-circle-xmark" style="margin-right:5px;"></i>Di luar radius kampus (' + Math.round(jarak) + 'm)</span>';

      updateMapUser(currentLat, currentLng);
    },
    err => {
      const msg = err.code === 1 ? 'Izin lokasi GPS ditolak'
                : err.code === 2 ? 'Lokasi GPS tidak tersedia'
                : 'Batas waktu GPS terlampaui';
      const slText = document.getElementById('status-lokasi-text');
      if (slText) {
        slText.innerHTML = '<span style="color:#dc2626;font-weight:600"><i class="fa-solid fa-circle-exclamation" style="margin-right:5px;"></i>' + msg + '</span>';
      }
      const kText = document.getElementById('koordinat-text');
      if (kText) kText.textContent = msg;
    },
    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
  );
}

function hitungJarak(lat1, lng1, lat2, lng2) {
  const R    = 6371000;
  const dLat = deg2rad(lat2 - lat1);
  const dLng = deg2rad(lng2 - lng1);
  const a    = Math.sin(dLat / 2) ** 2 +
               Math.cos(deg2rad(lat1)) * Math.cos(deg2rad(lat2)) *
               Math.sin(dLng / 2) ** 2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}
function deg2rad(d) { return d * Math.PI / 180; }

// ============================================
// KAMERA BIOMETRIK
// ============================================
async function bukaKamera() {
  const alertEl = document.getElementById('absensi-alert');
  hideAlert(alertEl);

  const mediaOK = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);

  if (!mediaOK) {
    showToast('Kamera butuh HTTPS. Silakan pilih berkas foto.', 'warning');
    const inp = document.getElementById('input-foto');
    inp.setAttribute('capture', 'user');
    inp.click();
    return;
  }

  try {
    kameraStream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
      audio: false
    });
    document.getElementById('video-stream').srcObject = kameraStream;
    document.getElementById('kamera-wrap').style.display  = 'block';
    document.getElementById('foto-actions').style.display = 'none';
    const kh = document.getElementById('kamera-hint');
    if (kh) kh.style.display = 'none';
  } catch (e) {
    let msg = 'Gagal membuka kamera: ' + e.message;
    if (e.name === 'NotAllowedError') {
      msg = 'Izin kamera ditolak. Berikan akses izin kamera pada browser Anda.';
    } else if (e.name === 'NotFoundError') {
      msg = 'Kamera tidak terdeteksi. Silakan gunakan tombol "Unggah Berkas".';
    } else if (e.name === 'NotReadableError' || (e.message && e.message.toLowerCase().includes('timeout'))) {
      msg = 'Kamera tidak merespons atau sedang digunakan aplikasi lain (Zoom/Meet/Camera). Silakan tutup aplikasi tersebut atau gunakan tombol "Unggah Berkas".';
    }
    showAlert(alertEl, msg, 'error');
    showToast(msg, 'error', 4500);
  }
}

function tutupKamera() {
  if (kameraStream) {
    kameraStream.getTracks().forEach(t => t.stop());
    kameraStream = null;
  }
  const kw = document.getElementById('kamera-wrap');
  const fa = document.getElementById('foto-actions');
  const kh = document.getElementById('kamera-hint');
  if (kw) kw.style.display = 'none';
  if (fa) fa.style.display = 'flex';
  if (kh) kh.style.display = 'block';
}

function ambilFoto() {
  const video  = document.getElementById('video-stream');
  const canvas = document.getElementById('canvas-foto');
  canvas.width  = video.videoWidth  || 640;
  canvas.height = video.videoHeight || 480;
  canvas.getContext('2d').drawImage(video, 0, 0);
  fotoBase64 = canvas.toDataURL('image/jpeg', 0.85);
  tampilkanPreview(fotoBase64);
  tutupKamera();
}

function uploadFotoFile(event) {
  const file = event.target.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    const img = new Image();
    img.onload = () => {
      // Skala gambar maksimal 640px agar ringan, cepat, dan aman untuk database
      const maxDim = 640;
      let w = img.width;
      let h = img.height;
      if (w > maxDim || h > maxDim) {
        if (w > h) {
          h = Math.round((h * maxDim) / w);
          w = maxDim;
        } else {
          w = Math.round((w * maxDim) / h);
          h = maxDim;
        }
      }
      const canvas = document.createElement('canvas');
      canvas.width = w;
      canvas.height = h;
      const ctx = canvas.getContext('2d');
      ctx.drawImage(img, 0, 0, w, h);
      fotoBase64 = canvas.toDataURL('image/jpeg', 0.85);
      tampilkanPreview(fotoBase64);
    };
    img.src = e.target.result;
  };
  reader.readAsDataURL(file);
}

function tampilkanPreview(src) {
  document.getElementById('foto-preview').src = src;
  document.getElementById('foto-preview-wrap').style.display = 'block';
  document.getElementById('foto-placeholder').style.display  = 'none';
  const indicator = document.getElementById('foto-status-indicator');
  if (indicator) {
    indicator.innerHTML = '<span style="color:#1e5a40;font-weight:700;"><i class="fa-solid fa-circle-check"></i> Foto Siap</span>';
  }
}

function hapusFoto() {
  fotoBase64 = null;
  document.getElementById('foto-preview').src = '';
  document.getElementById('foto-preview-wrap').style.display = 'none';
  document.getElementById('foto-placeholder').style.display  = 'flex';
  const inp = document.getElementById('input-foto');
  if (inp) inp.value = '';
  const indicator = document.getElementById('foto-status-indicator');
  if (indicator) {
    indicator.textContent = 'Wajib Dilampirkan';
  }
}

// ============================================
// PROSES PRESENSI (MASUK & PULANG)
// ============================================
async function doAbsensi() {
  const alertEl = document.getElementById('absensi-alert');
  const btn     = document.getElementById('btn-absen');
  hideAlert(alertEl);

  if (currentAttendanceStage === 'selesai') {
    showModalPopup({
      title: 'PRESENSI LENGKAP',
      subtitle: 'Seluruh rangkaian presensi hari ini telah terekam dan selesai.',
      type: 'info',
      confirmText: 'Tutup'
    });
    return;
  }

  if (!currentLat || !currentLng) {
    showAlert(alertEl, 'Lokasi GPS belum terdeteksi. Tekan "Perbarui Titik Koordinat" terlebih dahulu.', 'error');
    showModalPopup({
      title: 'LOKASI DIPERLUKAN',
      subtitle: 'Sistem membutuhkan koordinat GPS perangkat untuk validasi geofence kampus.',
      type: 'error',
      detailsHtml: `
        <div class="modal-row"><span class="modal-lbl">Status:</span><span class="modal-val" style="color:#9c2436;">GPS Tidak Terdeteksi</span></div>
        <div class="modal-row"><span class="modal-lbl">Petunjuk:</span><span class="modal-val">Klik 'Perbarui Titik Koordinat' dan izinkan browser membaca lokasi.</span></div>
      `,
      confirmText: 'Perbarui Lokasi',
      onConfirm: () => refreshLokasi()
    });
    return;
  }

  if (!fotoBase64) {
    showAlert(alertEl, 'Swafoto biometrik wajib dilampirkan sebelum konfirmasi presensi.', 'error');
    showModalPopup({
      title: 'SWAFOTO DIPERLUKAN',
      subtitle: 'Mohon ambil foto selfie melalui kamera atau unggah berkas untuk verifikasi kehadiran.',
      type: 'error',
      detailsHtml: `
        <div class="modal-row"><span class="modal-lbl">Status:</span><span class="modal-val" style="color:#9c2436;">Belum Ada Swafoto</span></div>
        <div class="modal-row"><span class="modal-lbl">Ketentuan:</span><span class="modal-val">Wajah tampak jelas menghadap kamera.</span></div>
      `,
      confirmText: 'Buka Kamera Sekarang',
      onConfirm: () => bukaKamera()
    });
    return;
  }

  const stageLabel = (currentAttendanceStage === 'pulang') ? 'Presensi Pulang' : 'Presensi Masuk';
  btn.disabled  = true;
  btn.innerHTML = `<span class="spinner"></span>Memvalidasi ${stageLabel}…`;

  try {
    const res = await postJSON(`${API_BASE}/absensi.php`, {
      user_id:   currentUser.user_id,
      latitude:  currentLat,
      longitude: currentLng,
      foto:      fotoBase64,
      tipe:      currentAttendanceStage
    });

    if (res.status === 'success') {
      const d = res.data;
      const isPulang = (d.tipe === 'pulang' || currentAttendanceStage === 'pulang');

      // Tampilkan Modal Popup Sukses Mewah
      if (isPulang) {
        showModalPopup({
          title: 'PRESENSI PULANG BERHASIL',
          subtitle: 'Presensi kepulangan Anda telah diverifikasi dan terekam dalam sistem.',
          type: 'success',
          detailsHtml: `
            <div class="modal-row"><span class="modal-lbl">Nama Personel:</span><span class="modal-val">${escapeHtml(currentUser.nama)}</span></div>
            <div class="modal-row"><span class="modal-lbl">NIM / ID:</span><span class="modal-val">${escapeHtml(currentUser.nim)}</span></div>
            <div class="modal-row"><span class="modal-lbl">Waktu Pulang:</span><span class="modal-val">${d.waktu_pulang || d.waktu || 'Baru Saja'}</span></div>
            <div class="modal-row"><span class="modal-lbl">Jarak Geofence:</span><span class="modal-val">${d.jarak || '-'}</span></div>
            <div class="modal-row"><span class="modal-lbl">Status Kehadiran:</span><span class="modal-val" style="color:#1e5a40;">LENGKAP (PULANG)</span></div>
          `,
          confirmText: 'Selesai'
        });
      } else {
        const isHadir = (d.status === 'hadir');
        showModalPopup({
          title: 'PRESENSI MASUK BERHASIL',
          subtitle: 'Kehadiran masuk Anda telah tervalidasi. Presensi pulang akan dibuka selanjutnya.',
          type: 'success',
          detailsHtml: `
            <div class="modal-row"><span class="modal-lbl">Nama Personel:</span><span class="modal-val">${escapeHtml(currentUser.nama)}</span></div>
            <div class="modal-row"><span class="modal-lbl">NIM / ID:</span><span class="modal-val">${escapeHtml(currentUser.nim)}</span></div>
            <div class="modal-row"><span class="modal-lbl">Waktu Masuk:</span><span class="modal-val">${d.waktu}</span></div>
            <div class="modal-row"><span class="modal-lbl">Kategori Waktu:</span><span class="modal-val" style="color:${isHadir ? '#1e5a40' : '#8c3e51'};">${d.status.toUpperCase()}</span></div>
            <div class="modal-row"><span class="modal-lbl">Jarak Geofence:</span><span class="modal-val">${d.jarak}</span></div>
          `,
          confirmText: 'Lanjutkan ke Tahap Pulang'
        });
      }

      // Bersihkan swafoto yang sudah tersimpan
      hapusFoto();

      // Sinkronkan stage terbaru dari database
      await checkStatusHariIni();

    } else {
      showAlert(alertEl, res.message, 'error');
      showModalPopup({
        title: 'PRESENSI DITOLAK',
        subtitle: res.message || 'Presensi tidak dapat diproses oleh sistem.',
        type: 'error',
        detailsHtml: `
          <div class="modal-row"><span class="modal-lbl">Alasan:</span><span class="modal-val" style="color:#9c2436;">${escapeHtml(res.message || 'Validasi Gagal')}</span></div>
          <div class="modal-row"><span class="modal-lbl">Tahap:</span><span class="modal-val">${escapeHtml(stageLabel)}</span></div>
        `,
        confirmText: 'Coba Lagi'
      });
      showToast(res.message || 'Presensi gagal diproses', 'error');

      // Kembalikan tombol
      btn.disabled = false;
      if (currentAttendanceStage === 'pulang') {
        btn.innerHTML = '<i class="fa-solid fa-arrow-right-from-bracket" id="btn-absen-icon"></i> <span id="btn-absen-text">Konfirmasi Presensi Pulang</span>';
      } else {
        btn.innerHTML = '<i class="fa-solid fa-arrow-right-to-bracket" id="btn-absen-icon"></i> <span id="btn-absen-text">Konfirmasi Presensi Masuk</span>';
      }
    }
  } catch (e) {
    const errText = 'Gagal terhubung ke server: ' + e.message;
    showAlert(alertEl, errText, 'error');
    showModalPopup({
      title: 'KESALAHAN JARINGAN',
      subtitle: 'Tidak dapat mengirim permintaan ke server.',
      type: 'error',
      detailsHtml: `<div class="modal-row"><span class="modal-lbl">Pesan Error:</span><span class="modal-val">${escapeHtml(e.message)}</span></div>`,
      confirmText: 'Tutup'
    });
    showToast(errText, 'error');

    btn.disabled = false;
    if (currentAttendanceStage === 'pulang') {
      btn.innerHTML = '<i class="fa-solid fa-arrow-right-from-bracket" id="btn-absen-icon"></i> <span id="btn-absen-text">Konfirmasi Presensi Pulang</span>';
    } else {
      btn.innerHTML = '<i class="fa-solid fa-arrow-right-to-bracket" id="btn-absen-icon"></i> <span id="btn-absen-text">Konfirmasi Presensi Masuk</span>';
    }
  }
}

// ============================================
// PENGAJUAN IZIN TIDAK HADIR
// ============================================
function toggleFormIzin(forceState) {
  const panel = document.getElementById('panel-form-izin');
  if (!panel) return;

  const willShow = (forceState !== undefined)
    ? forceState
    : (panel.style.display === 'none' || panel.style.display === '');

  if (willShow) {
    if (currentAttendanceStage === 'selesai' || currentAttendanceStage === 'pulang' || currentAttendanceStage === 'izin') {
      const msg = (currentAttendanceStage === 'izin')
        ? 'Anda sudah mengajukan permohonan izin untuk hari ini.'
        : 'Anda sudah tercatat melakukan presensi hadir hari ini.';
      showToast(msg, 'warning');
      return;
    }
    panel.style.display = 'block';
    const alertEl = document.getElementById('izin-alert');
    if (alertEl) hideAlert(alertEl);

    setTimeout(() => {
      panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      const txt = document.getElementById('catatan-izin');
      if (txt) txt.focus();
    }, 150);
  } else {
    panel.style.display = 'none';
  }
}

function selectKategoriIzin(kategori, btn) {
  selectedKategoriIzin = kategori;
  document.querySelectorAll('.izin-pill-btn').forEach(b => b.classList.remove('active'));
  if (btn) btn.classList.add('active');

  const txt = document.getElementById('catatan-izin');
  if (txt) {
    if (kategori === 'sakit') {
      txt.placeholder = 'Tuliskan keterangan sakit atau istirahat dokter...';
    } else if (kategori === 'dispensasi') {
      txt.placeholder = 'Tuliskan nama kegiatan lomba / dinas / penugasan kampus...';
    } else {
      txt.placeholder = 'Tuliskan keperluan pribadi / keluarga yang mendesak...';
    }
  }
}

async function submitIzin() {
  const alertEl = document.getElementById('izin-alert');
  const btn     = document.getElementById('btn-submit-izin');
  const txt     = document.getElementById('catatan-izin');
  const catatan = txt ? txt.value.trim() : '';

  if (!currentUser) {
    showToast('Silakan login terlebih dahulu', 'error');
    return;
  }

  hideAlert(alertEl);

  if (!catatan) {
    showAlert(alertEl, 'Catatan / keterangan alasan izin wajib diisi.', 'error');
    showToast('Catatan alasan izin wajib diisi', 'error');
    if (txt) txt.focus();
    return;
  }

  const labelKategori = (selectedKategoriIzin === 'sakit')
    ? 'Sakit'
    : ((selectedKategoriIzin === 'dispensasi') ? 'Dispensasi' : 'Izin Keperluan');

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Mengirim…';

  try {
    const res = await postJSON(`${API_BASE}/absensi.php`, {
      user_id:  currentUser.user_id,
      tipe:     'izin',
      kategori: selectedKategoriIzin,
      catatan:  catatan
    });

    if (res.status === 'success') {
      showModalPopup({
        title: 'PERMOHONAN IZIN DITERIMA',
        subtitle: 'Pengajuan izin tidak hadir Anda telah berhasil dicatat dalam sistem.',
        type: 'success',
        detailsHtml: `
          <div class="modal-row"><span class="modal-lbl">Nama Personel:</span><span class="modal-val">${escapeHtml(currentUser.nama)}</span></div>
          <div class="modal-row"><span class="modal-lbl">NIM / ID:</span><span class="modal-val">${escapeHtml(currentUser.nim)}</span></div>
          <div class="modal-row"><span class="modal-lbl">Kategori Izin:</span><span class="modal-val" style="color:#b8853b;text-transform:uppercase;">${escapeHtml(labelKategori)}</span></div>
          <div class="modal-row"><span class="modal-lbl">Alasan / Catatan:</span><span class="modal-val">${escapeHtml(catatan)}</span></div>
          <div class="modal-row"><span class="modal-lbl">Waktu Pengajuan:</span><span class="modal-val">${res.data?.waktu || 'Hari ini'}</span></div>
        `,
        confirmText: 'Mengerti'
      });

      showToast(`Izin (${labelKategori}) berhasil diajukan`, 'success');
      if (txt) txt.value = '';
      toggleFormIzin(false);

      // Sinkronkan status presensi hari ini
      await checkStatusHariIni();

    } else {
      showAlert(alertEl, res.message || 'Gagal mengajukan izin', 'error');
      showToast(res.message || 'Gagal mengajukan izin', 'error');
    }
  } catch (e) {
    const errText = 'Gagal terhubung ke server: ' + e.message;
    showAlert(alertEl, errText, 'error');
    showToast(errText, 'error');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> <span id="btn-submit-izin-text">Kirim Izin</span>';
  }
}

// ============================================
// RIWAYAT PRESENSI LENGKAP
// ============================================
async function loadRiwayat() {
  const container = document.getElementById('riwayat-list');
  if (!container) return;

  container.innerHTML =
    '<div class="empty-state"><span class="spinner" style="border-color:#e1d7da;border-top-color:#784d5c"></span> Memuat riwayat…</div>';

  try {
    const res = await fetchJSON(`${API_BASE}/riwayat.php?user_id=${currentUser.user_id}`);
    if (res.status !== 'success' || !res.data || !res.data.length) {
      container.innerHTML = '<div class="empty-state">Belum ada riwayat absensi tercatat</div>';
      return;
    }

    container.innerHTML = res.data.map(item => {
      const tglMsk = new Date(item.waktu).toLocaleString('id-ID', {
        weekday: 'short', day: 'numeric', month: 'short',
        year: 'numeric', hour: '2-digit', minute: '2-digit'
      });
      const tglPlg = item.waktu_pulang ? new Date(item.waktu_pulang).toLocaleString('id-ID', {
        hour: '2-digit', minute: '2-digit'
      }) : null;

      const stLower = (item.status || '').toLowerCase();
      const isIzinItem = ['izin', 'sakit', 'dispensasi'].includes(stLower);

      if (isIzinItem) {
        const iconIzin = (stLower === 'sakit')
          ? 'fa-heart-pulse'
          : ((stLower === 'dispensasi') ? 'fa-building-columns' : 'fa-clipboard-user');
        const labelIzin = (stLower === 'sakit')
          ? 'SAKIT'
          : ((stLower === 'dispensasi') ? 'DISPENSASI' : 'KEPERLUAN');

        return `
          <div class="riwayat-item">
            <div class="riwayat-foto-ph" style="background:rgba(212,163,98,0.12);color:var(--gold-dark);">
              <i class="fa-solid ${iconIzin}"></i>
            </div>
            <div class="riwayat-info">
              <div class="ri-tanggal">${tglMsk}</div>
              <div class="ri-status" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                <span class="badge" style="background:#fef7ea;color:#b8853b;border:1px solid rgba(212,163,98,0.4);font-weight:700;">
                  <i class="fa-solid ${iconIzin}" style="margin-right:4px;"></i>IZIN: ${labelIzin}
                </span>
              </div>
              <div class="ri-lokasi" style="color:var(--obsidian);font-size:11.5px;margin-top:2px;">
                <i class="fa-solid fa-comment-dots" style="margin-right:4px;color:var(--gold-dark);"></i>Catatan: ${escapeHtml(item.catatan || 'Izin tidak hadir')}
              </div>
            </div>
          </div>`;
      }

      const badgeCls = item.status === 'hadir' ? 'badge-hadir' : 'badge-terlambat';
      const fotoEl   = item.foto
        ? `<img class="riwayat-foto" src="../${item.foto}" alt="foto masuk" onerror="this.style.display='none'">`
        : `<div class="riwayat-foto-ph"><i class="fa-solid fa-user"></i></div>`;

      const pulangBadge = tglPlg
        ? `<span class="badge" style="background:#f4efe6;color:#5e5750;border:1px solid rgba(197,155,86,0.3);"><i class="fa-solid fa-arrow-right-from-bracket" style="margin-right:4px;"></i>Pulang: ${tglPlg} WIB</span>`
        : `<span class="badge" style="background:#faf8f5;color:#8e8479;border:1px dashed rgba(20,18,16,0.2);">Belum Pulang</span>`;

      return `
        <div class="riwayat-item">
          ${fotoEl}
          <div class="riwayat-info">
            <div class="ri-tanggal">${tglMsk}</div>
            <div class="ri-status" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
              <span class="badge ${badgeCls}">MASUK: ${item.status.toUpperCase()}</span>
              ${pulangBadge}
            </div>
            <div class="ri-lokasi">
              <i class="fa-solid fa-location-dot" style="margin-right:4px"></i>${parseFloat(item.latitude).toFixed(5)}, ${parseFloat(item.longitude).toFixed(5)}
            </div>
          </div>
        </div>`;
    }).join('');
  } catch (e) {
    container.innerHTML =
      `<div class="empty-state" style="color:#9c2436">Gagal memuat: ${escapeHtml(e.message)}</div>`;
    showToast('Gagal memuat riwayat', 'error');
  }
}

// ============================================
// HELPERS
// ============================================
async function postJSON(url, data) {
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  });
  if (!res.ok) throw new Error('HTTP ' + res.status);
  return res.json();
}

async function fetchJSON(url) {
  const res = await fetch(url);
  if (!res.ok) throw new Error('HTTP ' + res.status);
  return res.json();
}

function showAlert(el, msg, type) {
  if (!el) return;
  el.textContent = msg;
  el.className   = 'alert alert-' + type + ' show';
}

function hideAlert(el) {
  if (!el) return;
  el.className   = 'alert';
  el.textContent = '';
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}
