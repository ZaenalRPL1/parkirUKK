/**
 * Efek suara notifikasi "Benar" (berhasil) dan "Salah" (gagal/error).
 *
 * Cara pakai:
 * 1. Taruh file audiomu di assets/audio/benar.mp3 dan assets/audio/salah.mp3
 * 2. Panggil manual di halaman mana pun (setelah script ini dimuat):
 *      mainkanSuaraBenar();
 *      mainkanSuaraSalah();
 *
 * Otomatis juga akan berbunyi sendiri kalau di halaman ada elemen
 * dengan class "alert-success" (bunyi benar) atau "alert-danger" (bunyi salah)
 * saat halaman selesai dimuat -- jadi tidak perlu ubah kode di form manapun.
 *
 * Kalau file audio belum ada / gagal dimuat, otomatis fallback ke nada
 * sintesis bawaan (Web Audio API) supaya tidak diam saja.
 */

// ==== GANTI PATH INI KALAU NAMA FOLDER PROJECT KAMU BEDA DARI BASE_URL DI koneksi.php ====
const SUARA_BENAR_URL = '/parkir_terminal_parangtritis/assets/audio/benar.wav';
const SUARA_SALAH_URL = '/parkir_terminal_parangtritis/assets/audio/salah.wav';

function _mainkanFileAtauFallback(url, fallbackFn) {
    const audio = new Audio(url);
    let sudahFallback = false;
    audio.addEventListener('error', function () {
        if (!sudahFallback) { sudahFallback = true; fallbackFn(); }
    });
    audio.play().catch(function () {
        if (!sudahFallback) { sudahFallback = true; fallbackFn(); }
    });
}

function mainkanSuaraBenar() {
    _mainkanFileAtauFallback(SUARA_BENAR_URL, _fallbackSuaraBenar);
}

function mainkanSuaraSalah() {
    _mainkanFileAtauFallback(SUARA_SALAH_URL, _fallbackSuaraSalah);
}

// ---- Nada sintesis cadangan (dipakai otomatis kalau file MP3 belum ada) ----
function _fallbackSuaraBenar() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const now = ctx.currentTime;
        [523.25, 659.25, 783.99].forEach(function (freq, i) {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.value = freq;
            const start = now + i * 0.09;
            gain.gain.setValueAtTime(0.001, start);
            gain.gain.exponentialRampToValueAtTime(0.18, start + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.28);
            osc.connect(gain).connect(ctx.destination);
            osc.start(start);
            osc.stop(start + 0.3);
        });
    } catch (e) { /* abaikan */ }
}

function _fallbackSuaraSalah() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const now = ctx.currentTime;
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'square';
        osc.frequency.setValueAtTime(220, now);
        osc.frequency.linearRampToValueAtTime(110, now + 0.35);
        gain.gain.setValueAtTime(0.001, now);
        gain.gain.exponentialRampToValueAtTime(0.15, now + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.4);
        osc.connect(gain).connect(ctx.destination);
        osc.start(now);
        osc.stop(now + 0.4);
    } catch (e) { /* abaikan */ }
}

// Deteksi otomatis alert Bootstrap yang sudah ada di halaman (alert-success / alert-danger)
document.addEventListener('DOMContentLoaded', function () {
    if (document.querySelector('.alert-danger')) {
        mainkanSuaraSalah();
    } else if (document.querySelector('.alert-success')) {
        mainkanSuaraBenar();
    }
});