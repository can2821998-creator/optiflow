# OptiFlow 4.18.0 / OptiFlow Pro 5.4.0: Beni hatırla ve Medula şifresi

## 1. OptiFlow'da "Beni hatırla"

Giriş iki adımlıdır ve ikisinde de bir kutu var:

| Ekran | Kutu | Ne yapar |
|---|---|---|
| Mağaza girişi | **Bu bilgisayarda mağazayı hatırla** | 30 gün mağaza e-postası ve şifresi sorulmaz. |
| Personel girişi | **Beni hatırla** | 30 gün kullanıcı adı ve parola sorulmaz. |

- **OptiFlow Pro'da** iki kutu da baştan işaretlidir, çünkü mağazanın kendi bilgisayarıdır. Tarayıcıda işaretsiz gelir.
- Süre, bilgisayar her kullanıldığında yeniden 30 güne uzar. Her gün açılan bir bilgisayar hiç şifre sormaz.
- **Çıkış** yalnızca o bilgisayardaki personeli unutur; mağaza hatırlanmaya devam eder. Personel değişecekse "Çıkış" yeterlidir.
- Personel giriş ekranındaki **Farklı mağaza** düğmesi mağazayı da unutturur.
- **Profilim › Beni hatırlayan cihazlar:** Hangi bilgisayar ve telefonların sizi hatırladığını gösterir. Cihazları tek tek ya da hepsini birden unutabilirsiniz.
- Parola değişince öteki cihazların hepsi unutulur; parolayı değiştirdiğiniz cihaz hatırlanmaya devam eder.
  Merkez panelden mağaza şifresi sıfırlanınca da o mağazayı hatırlayan cihazların hepsi unutulur.
- **Ayarlar › Genel › Beni hatırla:** Kapatılırsa kutu görünmez ve hatırlanan bütün cihazlar unutulur.
- Ortak kullanılan bilgisayarlarda (internet kafe, başkasının telefonu) kutuyu işaretlemeyin.

## 2. OptiFlow Pro'da Medula şifresi

Chrome'un şifre kaydetmesi gibi çalışır:

1. Medula'ya her zamanki gibi kullanıcı adınızı, şifrenizi ve güvenlik kodunu yazıp **Giriş**'e basın.
2. Giriş başarılı olunca OptiFlow Pro sorar: **Kaydet / Şimdi değil / Bu bilgisayarda sorma**.
3. Bir sonraki girişte kullanıcı adı ve şifre kendiliğinden yazılır, imleç güvenlik kodu kutusuna gelir.
   **Güvenlik kodunu siz yazıp Giriş'e basarsınız.** SGK resimli kodu bu yüzden koyuyor; atlamak mümkün değil ve doğru da olmaz.
4. SGK şifrenizi değiştirmenizi istediğinde yeni şifreyi iki kez yazarsınız. OptiFlow Pro bu durumda kayıtlı şifreyi güncellemek isteyip istemediğinizi sorar.
   Şifreyi Medula'da başka bir yoldan değiştirdiyseniz, bir sonraki girişte yeni şifreyi elle yazın; giriş başarılı olunca yine "güncellensin mi?" diye sorar.

**Güvenlik:**
- Şifre yalnızca **o bilgisayarda**, Windows hesabınıza bağlı şifrelemeyle (DPAPI) saklanır. Dosya başka bir bilgisayara ya da başka bir Windows kullanıcısına kopyalanırsa açılamaz.
- OptiFlow sunucusuna, kayıt (log) dosyalarına ya da tanılama paketine **hiç gitmez**.
- Silmek için: **Menü (⋯) › SGK / Medula › Kayıtlı Medula şifresi… › Sil.**
  Teklifi tamamen kapatmak için aynı menüde **"Medula şifresini kaydetmeyi öner"** seçeneğinin işaretini kaldırın.
- "Medula oturumunu temizle" ve "Medula'yı sıfırla" kayıtlı şifreye dokunmaz.

## 3. İlk kullanımda dikkat

Gerçek Medula giriş ekranında kullanıcı adı alanı, şifre alanının hemen önündeki yazı kutusu olarak bulunur; güvenlik kodu alanı ise hemen sonrakidir.
İlk girişte kutuların doğru dolduğuna bakın. Yanlış kutu dolarsa haber verin, ekranın görüntüsüne göre düzeltilir.
