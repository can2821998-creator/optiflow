<?php
declare(strict_types=1);

/* Sahte ÜTS: gerçek servisin kurallarını taklit eden taşıyıcı ($GLOBALS['__uts_tasiyici']).
   - utsToken yanlışsa 401
   - Alma: yalnızca bekleyen VBI kabul edilir
   - Tüketiciye verme: ürün mağazanın ÜTS stoğunda olmalı (aynı ürün iki kez verilemez), GIT zorunlu
   - Tüketiciden iade: geçerli TID gerekir
   - İmha: GRK + BNO zorunlu; verme: KUN + BNO zorunlu */
final class SahteUts
{
    public string $token = 'dogru-token';
    /** @var array<string,array> VBI → ürün */
    public array $bekleyen = [];
    /** @var array<string,bool> "UNO|SNO/LNO" → mağaza stoğunda mı */
    public array $stok = [];
    /** @var array<string,string> BID → ürün anahtarı (tüketiciye verme) */
    public array $tv = [];
    public array $istekler = [];
    /** Sıradaki çağrılar için zorla dönecek yanıtlar: [durum, govde] */
    public array $zorla = [];

    public function __invoke(string $url, array $basliklar, string $govde): array
    {
        $j = json_decode($govde, true) ?: [];
        $this->istekler[] = ['url' => $url, 'govde' => $j, 'basliklar' => $basliklar];
        if ($this->zorla) {
            [$d, $g] = array_shift($this->zorla);
            return ['durum' => $d, 'govde' => $g, 'hata' => $d === 0 ? 'Connection timed out' : ''];
        }
        if (($basliklar['utsToken'] ?? '') !== $this->token) {
            return $this->yanit(401, 'HATA', 'UTS-0401', 'Geçersiz ya da süresi dolmuş token');
        }
        $yol = (string) parse_url($url, PHP_URL_PATH);
        $anahtar = static fn(array $x): string => ($x['UNO'] ?? '') . '|' . ($x['SNO'] ?? ($x['LNO'] ?? ''));
        switch (true) {
            case str_ends_with($yol, '/kabulEdilecekTekilUrun'):
                return ['durum' => 200, 'govde' => json_encode(['SNC' => array_values($this->bekleyen), 'MSJ' => [['TIP' => 'BILGI', 'KOD' => 'UTS-0000', 'MET' => 'Sorgulama başarılı']]]), 'hata' => ''];
            case str_ends_with($yol, '/alma/ekle'):
                $v = $j['VBI'] ?? '';
                if (!isset($this->bekleyen[$v])) {
                    return $this->yanit(200, 'HATA', 'UTS-1201', 'Kabul edilecek verme bildirimi bulunamadı');
                }
                $this->stok[$anahtar($this->bekleyen[$v])] = true;
                unset($this->bekleyen[$v]);
                return $this->basari();
            case str_ends_with($yol, '/tuketiciyeVerme/ekle'):
                if (empty($j['GIT']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $j['GIT'])) {
                    return $this->yanit(400, 'HATA', 'UTS-0100', 'GIT alanı zorunludur');
                }
                $k = $anahtar($j);
                if (empty($this->stok[$k])) {
                    return $this->yanit(200, 'HATA', 'UTS-1305', 'Ürün kurum stoğunda bulunamadı veya daha önce tüketiciye verilmiş');
                }
                $this->stok[$k] = false;
                $b = $this->basari($id);
                $this->tv[$id] = $k;
                return $b;
            case str_ends_with($yol, '/tuketicidenIadeAlma/ekle'):
                $t = $j['TID'] ?? '';
                if (!isset($this->tv[$t])) {
                    return $this->yanit(200, 'HATA', 'UTS-1402', 'Tüketiciye verme bildirimi bulunamadı');
                }
                $this->stok[$this->tv[$t]] = true;
                unset($this->tv[$t]);
                return $this->basari();
            case str_ends_with($yol, '/imhaBertaraf/ekle'):
                if (empty($j['GRK']) || empty($j['BNO'])) {
                    return $this->yanit(400, 'HATA', 'UTS-0100', 'GRK ve BNO zorunludur');
                }
                $this->stok[$anahtar($j)] = false;
                return $this->basari();
            case str_ends_with($yol, '/verme/ekle'):
                if (empty($j['KUN']) || empty($j['BNO'])) {
                    return $this->yanit(400, 'HATA', 'UTS-0100', 'KUN ve BNO zorunludur');
                }
                $this->stok[$anahtar($j)] = false;
                return $this->basari();
        }
        return ['durum' => 404, 'govde' => 'Not Found', 'hata' => ''];
    }

    public function basari(?string &$id = null): array
    {
        $id = uts_uuid4();
        return ['durum' => 200, 'govde' => json_encode(['SNC' => ['BID' => $id], 'MSJ' => [['TIP' => 'BILGI', 'KOD' => 'UTS-0000', 'MET' => 'İşlem başarılı']]]), 'hata' => ''];
    }

    public function yanit(int $durum, string $tip, string $kod, string $met): array
    {
        return ['durum' => $durum, 'govde' => json_encode(['MSJ' => [['TIP' => $tip, 'KOD' => $kod, 'MET' => $met]]], JSON_UNESCAPED_UNICODE), 'hata' => ''];
    }

    public function gonder(string $uno, string $sno, string $mme, string $kurum = 'Toptancı A.Ş.'): string
    {
        $v = uts_uuid4();
        $this->bekleyen[$v] = ['UNO' => $uno, 'SNO' => $sno, 'VBI' => $v, 'AKU' => $kurum, 'MME' => $mme, 'BNO' => 'FTR2026001', 'GKN' => '1234567890'];
        return $v;
    }

    public function sayac(string $yolSonu): int
    {
        return count(array_filter($this->istekler, static fn($i) => str_ends_with((string) parse_url($i['url'], PHP_URL_PATH), $yolSonu)));
    }
}
