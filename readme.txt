# BKM Aksiyon Takip WordPress Eklentisi

WordPress ile aksiyon ve görev takip sistemi eklentisi.

## Özellikler

### Yönetici Paneli
- **Aksiyon Takip**: Ana dashboard ile genel bakış
- **Aksiyon Ekle**: Detaylı aksiyon oluşturma formu
- **Kategoriler**: Kategori yönetim sistemi
- **Performanslar**: Performans verileri yönetimi
- **Raporlar**: Görsel grafikler ve analytics

### Aksiyon Formu Alanları
- Aksiyonu Tanımlayan (Kullanıcı seçimi)
- Sıra No (Otomatik ID)
- Aksiyon Önem Derecesi (1-2-3)
- Aksiyon Açılma Tarihi
- Hafta numarası
- Kategori seçimi
- Aksiyon Sorumlusu (Çoklu kullanıcı)
- Tespit konusu (3 satır)
- Aksiyon açıklaması (5 satır)
- Hedef tarih
- Kapanma tarihi
- Performans seçimi
- İlerleme durumu (% progress bar)
- Notlar (5 satır)

### Frontend Dashboard
- Shortcode: `[aksiyon_takipx]`
- WordPress login entegrasyonu
- Modern ve responsive tasarım
- Aksiyon takip tablosu
- Görev yönetim sistemi
- Gerçek zamanlı güncellemeler

### Görev Sistemi
Her aksiyona sınırsız görev eklenebilir:
- Görev içeriği
- Başlangıç tarihi
- Sorumlu kişi
- Hedef bitiş tarihi
- İlerleme durumu (% progress bar)
- Otomatik gerçek bitiş tarihi

### Yetkilendirme
- Sadece Yönetici ve Editör aksiyon ekleyebilir
- Görev sahipleri kendi görevlerini yönetebilir
- Güvenli erişim kontrolleri

### E-posta Bildirimleri
- Yeni aksiyon oluşturulduğunda
- Görev eklendiğinde
- Görev tamamlandığında
- İlgili kullanıcılara otomatik bildirim

## Kurulum

1. Plugin dosyalarını `/wp-content/plugins/bkm-aksiyon-takip/` klasörüne yükleyin
2. WordPress admin panelinden eklentiyi etkinleştirin
3. Veritabanı tabloları otomatik oluşturulacaktır
4. Varsayılan kategoriler ve performans verileri eklenecektir

## Kullanım

### Admin Panel
- WordPress admin menüsünden "Aksiyon Takip" bölümüne gidin
- Yeni aksiyon eklemek için "Aksiyon Ekle" sayfasını kullanın
- Kategoriler ve performans verilerini yönetin
- Raporlar sayfasından analytics görüntüleyin

### Frontend
- Herhangi bir sayfa/yazıya `[aksiyon_takipx]` shortcode'unu ekleyin
- Kullanıcılar WordPress giriş bilgileriyle sisteme erişebilir
- Aksiyonları görüntüleyebilir ve görevleri yönetebilir

## Gereksinimler

- WordPress 5.0 veya üzeri
- PHP 7.4 veya üzeri
- MySQL 5.7 veya üzeri

## Veritabanı Tabloları

Plugin aşağıdaki tabloları oluşturur:
- `wp_bkm_actions`: Aksiyonlar
- `wp_bkm_categories`: Kategoriler
- `wp_bkm_performance`: Performans verileri
- `wp_bkm_tasks`: Görevler

## Güvenlik

- WordPress nonce kullanımı
- Kullanıcı yetki kontrolleri
- SQL injection koruması
- XSS koruması

## Destek

Herhangi bir sorun için GitHub repository'sindeki Issues bölümünü kullanın.

## Lisans

GPL v2 or later

## Versiyon

1.0.0 - İlk sürüm