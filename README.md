# InstaApp (mini Instagram clone)

Fitur:
- Register & Login (JWT)
- Posting teks + gambar (upload)
- Like & Komentar (toggle like, list komentar)
- Autentikasi & hak akses (hapus hanya oleh pemilik post)
- Backend: PHP (murni) + SQLite
- Frontend: HTML + Tailwind + Vanilla JS
- API sederhana (REST-ish)

## Cara Menjalankan (tanpa web server tambahan)
Pastikan sudah terinstall **PHP 8+**.

```bash
cd InstaApp
php -S localhost:8000
```

Lalu buka: `http://localhost:8000/public/index.html`

> Catatan: API berada di `http://localhost:8000/api.php`

## Endpoint API (ringkas)
- POST `/api.php/register` — body JSON `{ username, password }`
- POST `/api.php/login` — body JSON `{ username, password }` => hasil `{ token }`
- GET `/api.php/posts` — daftar feed
- POST `/api.php/posts` — (auth) multipart form `{ caption, image }`
- DELETE `/api.php/posts/{id}` — (auth & owner only) hapus post
- POST `/api.php/posts/{id}/like` — (auth) toggle like
- POST `/api.php/posts/{id}/comments` — (auth) body JSON `{ text }`

## Struktur Folder
```
InstaApp/
├─ api.php
├─ config.php
├─ jwt.php
├─ utils.php
├─ instaapp.sqlite        # otomatis dibuat
├─ uploads/               # file gambar tersimpan di sini
└─ public/
   └─ index.html          # frontend
```

## Security Note
- Ganti `jwt_secret` di `config.php` saat production.
- Ini contoh edukasi/MVP; tambahkan rate-limit, validation, CSRF mitigations untuk produksi.
```

