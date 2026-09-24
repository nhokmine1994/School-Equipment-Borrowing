# CAU HINH DOMAIN: seb.io.vn

## Overview
Domain ban dang: seb.io.vn. Code SEB v2 tu thich ung domain goc (APP_ROOT tu detect) - khong can sua source.

## Camera can HTTPS
Browser chan getUserMedia (camera moc ta / quet QR) neu khong phai HTTPS.
Khuyen dung Cloudflare Tunnel - mien phi SSL, khong can public IP, khong mo port router.

## Apache vhost
Save vao: xampp/apache/conf/extra/httpd-vhosts.conf; uncomment Include conf/extra/httpd-vhosts.conf trong httpd.conf; restart Apache.

## DNS
- A record `seb.io.vn` phai tro ve IP cong cong cua may chu (khong dung `127.0.0.1`).
- Neu dung Cloudflare Tunnel, dung CNAME tro ve tunnel hostname do Cloudflare cap.
- Co the dung `www` lam CNAME ve `seb.io.vn`.

## Apache VirtualHost
- `DocumentRoot` phai la `C:/xampp/htdocs/SEB`, khong phai `C:/xampp/htdocs/SEB/dist`.
- Thu muc goc phai co `AllowOverride All` de `.htaccess` xu ly SPA, API va cac trang PHP.
- Sau khi sua cau hinh, chay `httpd.exe -t` va restart Apache.

## Bao mat khi public
- Doi password admin mac dinh (bcrypt)
- DB creds dung bien env SEB_DB_USER / SEB_DB_PASSWORD
- php.ini: session.cookie_secure=1, cookie_httponly=1, samesite=Lax

## Checklist deploy public:
- [ ] Restore DB + chay database/db_patch_v2.sql
- [ ] npm run build (React bundle)
- [ ] Apache vhost seb.io.vn + restart
- [ ] DNS tro domain (A record / CNAME)
- [ ] HTTPS ON
- [ ] Doi password admin mac dinh (admin/admin)
