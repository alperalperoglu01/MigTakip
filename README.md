# MigTakip Android (WebView + MigPack Otomatik Paket)

Bu repo:
- MigTakip web uygulamasını **değiştirmeden** WebView ile açar
- MigPack (package: `app.migpack`) içinde **Geçmiş Siparişlerim** ekranında görünen `Paketler (46)` yazısını Accessibility ile yakalar
- Yakalanan değeri, MigTakip web uygulamasına **session cookie** ile `POST /api/auto_packages.php` üzerinden kaydeder.

## Kurulum
1) `android/` klasörünü Android Studio ile açın.
2) Build alın.
3) Telefonda MigTakip açın, giriş yapın.
4) Android Ayarlar > Erişilebilirlik > MigTakip > Aç (1 kere).
5) MigPack'te gün detayını açınca MigTakip otomatik kaydeder.

## Notlar
- API endpoint: `web/public/api/auto_packages.php`
- Base URL: `Config.BASE_URL`
- MigPack package: `Config.MIGPACK_PACKAGE`
