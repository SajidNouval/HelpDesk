# RUNTIME SCORE ANALYSIS

## Query
"wifi lemot dan printer tidak mau print"

## Ringkasan Alur

1. `App\Services\Chatbot\AdvancedRetrievalService::retrieve`
   - Input: query mentah.
   - Proses: typo normalization, synonym normalization, multi-intent detection.
   - Output: dua intent terdeteksi: `["wifi lemot", "printer tidak mau print"]`.

2. `App\Services\Chatbot\AdvancedRetrievalService::multiIntentRetrieval`
   - Untuk setiap intent, memanggil `singleIntentRetrieval` secara terpisah.
   - Hasil akhir: balanced merge dari top hasil masing-masing intent.

3. `App\Services\Chatbot\AdvancedRetrievalService::singleIntentRetrieval`
   - Domain detection (`DomainDetectionService::detectDomain`).
   - Category filtering via `getAllowedCategories` dan `getDomainFilteredArticles`.
   - Query expansion (`expandQuery`) berdasarkan domain.
   - Document preparation (`prepareDocuments`) dan building TF-IDF vectors.
   - Query TF-IDF vector generation (`TfidfService::calculateQueryTFIDF`).
   - Scoring via `hybridRanking` dan `diversifyResults`.

## Kandidat Artikel Utama dari Seeder

### Wifi lemot
Berdasarkan seeder artikel, kandidat paling relevan adalah:
- `Solusi Internet Lambat pada Jaringan Wifi Kantor`
- `Cara Mengatasi Wifi Tidak Terhubung ke Perangkat`
- `Mengatasi Wifi Sering Putus Nyambung pada Jaringan Kantor`

Artikel ini muncul pada domain `wifi`/`Wifi & Jaringan` di seeder.

### Printer tidak mau print
Kandidat utama:
- `Troubleshooting Printer Tidak Mau Ngeprint (No Response)`
- `Cara Mengatasi Printer Offline dan Tidak Terdeteksi`
- `Cara Setting Printer Network (LAN/WiFi) di Kantor`

Artikel `Troubleshooting Printer Tidak Mau Ngeprint` paling cocok dengan frase query.

## Analisis Setiap Tahap

### 1. AdvancedRetrievalService

#### Input
- Query asli: `wifi lemot dan printer tidak mau print`

#### Proses
- `normalizeTypos($query)` — koreksi typo.
- `normalizeSynonyms($query)` — normalisasi sinonim seperti `lambat` → `lemot` jika ada.
- `detectMultiIntent($query)` memecah query dengan separator seperti `dan`, `atau`, `dengan`, `serta`, dan `,`.
- Karena ada `dan`, query dibagi menjadi:
  - `wifi lemot`
  - `printer tidak mau print`
- Untuk setiap intent, `singleIntentRetrieval` dijalankan.

#### Output
- Dua jalur retrieval terpisah.
- Hasil akhir adalah `balancedMerge` dari hasil tiap intent.

### 2. Domain Detection

#### Input
- Intent query: `wifi lemot` dan `printer tidak mau print`

#### Proses
- `DomainDetectionService::detectDomain` memanggil:
  - `preprocessor->normalizeTypos`
  - `applySynonymMapping`
  - `tokenizeQuery`
  - `scoreDomains` berdasarkan domain keyword.
- Domain keyword untuk `wifi` adalah kata seperti `wifi`, `wi-fi`, `wireless`, `wlan`, `hotspot`, `access point`, `ap`, `router wifi`.
- Domain keyword untuk `printer` adalah kata seperti `printer`, `printing`, `cetak`, `mencetak`, `epson`, `canon`, `ink`, `tinta`, `cartridge`, `toner`.

#### Output
- Domain yang terdeteksi kemungkinan besar:
  - `wifi` untuk intent `wifi lemot`
  - `printer` untuk intent `printer tidak mau print`
- `getAllowedCategories` menggunakan mapping domain ke kategori dari `AdvancedRetrievalService::$domainCategoryMap`.

### 3. Candidate Filtering

#### Input
- allowedCategories dari domain detection.

#### Proses
- `getDomainFilteredArticles` memanggil query Eloquent pada model `Article`.
- Filter berdasarkan category name `LOWER(TRIM(name))`.

#### Output
- Koleksi artikel yang diterima domain.
- Jika tidak ada kandidat, fallback ke semua artikel yang dipublish.

### 4. Query Expansion

#### Input
- Query ter-normalisasi dan domain.

#### Proses
- `AdvancedRetrievalService::expandQuery`
- Menambahkan expansion terms berdasarkan domain:
  - Untuk `wifi`: `internet jaringan hotspot koneksi router wireless lan wan`
  - Untuk `printer`: `print cetak scanner mencetak printing epson canon`
- Juga menambahkan ekspansi untuk token query jika token itu sendiri ada dalam `queryExpansionDict`.

#### Output
- Expanded query string yang digunakan untuk TF-IDF.

### 5. Document Preparation

#### Input
- Artikel kandidat dari database.

#### Proses
- `prepareDocuments` membentuk token dari:
  - `title` (diberi bobot 3x)
  - `keywords` (2x)
  - `excerpt` (1-2x, dengan `rand(0,1)`)
  - `content` (1x)
- `frequency` dihitung dari token gabungan.
- Vektor dokumen menyimpan `text`, `frequency`, `title_tokens`, `category_name`, dll.

#### Output
- Array dokumen siap untuk TF-IDF.

### 6. TF-IDF

#### Input
- `documents` dari `prepareDocuments`
- `expandedQuery`

#### Proses
- `TfidfService::buildTfidfVectors`
  - `calculateTF` untuk setiap dokumen.
  - `calculateIDF` untuk semua dokumen dalam korpus.
  - `calculateTFIDF` untuk setiap dokumen dengan formula `tf × idf`.
- `TfidfService::calculateQueryTFIDF`
  - Preprocess query ke token.
  - `calculateTF` untuk query.
  - `tf × idf` untuk query token.
- Term prioritas rendah diberi bobot 0.1 di `calculateTFIDF`.

#### Output
- `tfidfData['vectors']` untuk dokumen.
- `tfidfData['idf']` untuk korpus.
- `queryVector` untuk query.

#### Catatan penting
- `prepareDocuments` mengandung `rand(0,1)` saat menggandakan token excerpt.
- Ini membuat nilai TF dan TF-IDF dokumen tidak deterministik secara statis.
- Karena korpus artikel dan randomisasi runtime diperlukan, nilai numerik TF-IDF tidak dapat dihitung tanpa eksekusi.

### 7. Cosine Similarity

#### Input
- `queryVector` dari TF-IDF.
- `docVector` dari TF-IDF dokumen.

#### Proses
- `CosineSimilarityService::calculate`
  - merge term unik dari kedua vektor.
  - hitung `dotProduct = Σ (a_i × b_i)`.
  - hitung `magnitudeA = sqrt(Σ a_i^2)` dan `magnitudeB = sqrt(Σ b_i^2)`.
  - hasil = `dotProduct / (magnitudeA × magnitudeB)`.

#### Output
- float similarity antara 0.0 dan 1.0.

#### Catatan penting
- Vektor query dan dokumen bergantung pada TF-IDF runtime.
- Karena TF-IDF tidak dapat ditentukan statis, nilai cosine similarity juga tidak dapat diketahui statis.

### 8. Important Phrase / Phrase Boost

#### Input
- Query asli.
- Dokumen article title/excerpt/content.

#### Proses
- `ImportantPhraseService::detectPhrases`
  - Mencari frasa penting di query seperti `tidak mau`, `tidak bisa`, `tidak terhubung`, `putus nyambung`.
- `getPhraseBoostScore`
  - `calculatePhraseScore` memberi bonus lokasi frasa:
    - `TITLE_PHRASE_BONUS` 0.6 jika frasa ada di judul.
    - `PHRASE_MATCH_BONUS` 0.4 jika frasa ada di konten.
    - `EXACT_QUERY_PHRASE_BONUS` 0.8 jika full query phrase muncul di judul.
  - `calculateNgramOverlap` memberi bonus bigram/trigram.
  - Total boost dibatasi maksimal 1.0.

#### Output
- `phrase_boost` dan `ngram_boost`.

#### Catatan penting
- Nilai bergantung pada kecocokan frasa spesifik di dokumen runtime.
- Untuk judul `Troubleshooting Printer Tidak Mau Ngeprint`, kemungkinan besar ada phrase match, tetapi nilai persis tidak dapat diketahui statis.

### 9. Final Score

#### Formula yang digunakan di `AdvancedRetrievalService::hybridRanking`

```
Final Score =
  (CosineSimilarity × 0.30)
+ (TitleOverlap × 0.25)
+ (DomainMatch × 0.15)
+ (QueryCoverage × 0.15)
+ (ExactPhraseBonus × 0.10)
+ (Diversification × 0.05)
+ DomainPenalty
+ SecurityBoost
+ PhraseBoost
```

#### Komponen yang dapat ditelusuri:
- `CosineSimilarity` — dari `CosineSimilarityService::calculate`
- `TitleOverlap` — dari `AdvancedRetrievalService::calculateTitleOverlap`
- `DomainMatch` — dari `AdvancedRetrievalService::calculateDomainMatch`
- `QueryCoverage` — dari `AdvancedRetrievalService::calculateQueryCoverage`
- `ExactPhraseBonus` — dari `AdvancedRetrievalService::calculateExactPhraseBonus`
- `Diversification` — pada saat scoring selalu `0.05` sebelum bobot
- `DomainPenalty` — dari `AdvancedRetrievalService::calculateDomainPenalty`
- `SecurityBoost` — biasanya `0.0` untuk query ini karena bukan security intent
- `PhraseBoost` — dari `ImportantPhraseService::getPhraseBoostScore`

#### Catatan khusus
- `Diversification` di-assign statis `0.05` dalam `hybridRanking`.
- `SecurityBoost` hanya aktif bila `hasSecurityIntent($originalQuery)` true.
- `DomainPenalty` dapat bernilai 0.0, -0.5, atau -0.8 tergantung keyword/domain mismatch.

## Observasi Penting terhadap Seeder dan Mapping Domain

- Seeder kategori menggunakan nama `Wifi & Jaringan` dan `Printer`.
- `AdvancedRetrievalService::$domainCategoryMap` mengharapkan kategori nama seperti `wifi`, `internet`, `jaringan`, `hardware`.
- Ini berarti untuk artikel wifi, `calculateDomainMatch` kemungkinan besar akan menghasilkan `0.0` jika category name `Wifi & Jaringan` tidak cocok dengan allowed categories.
- Untuk artikel printer dengan category `Printer`, nilai `DomainMatch` kemungkinan besar `1.0` karena `allowedCategories` berisi `printer`.
- Jika domain filtering tidak menemukan artikel karena mismatch category mapping, sistem akan fallback ke semua artikel yang dipublish.

## Kesimpulan Nilai yang Dapat / Tidak Dapat Diketahui

- TF-IDF: Tidak. Membutuhkan korpus dokumen runtime, token frekuensi dokumen, dan random excerpt duplication.
- Cosine Similarity: Tidak. Membutuhkan vektor TF-IDF runtime query dan dokumen.
- Query Coverage: Tidak. Membutuhkan queryVector dan docVector runtime.
- Title Overlap: Tidak. Membutuhkan term list runtime dan judul dokumen.
- Domain Match: Ya untuk formula; namun nilai aktual untuk dokumen tertentu bergantung runtime pada category mapping. Untuk `Printer` candidate kemungkinan `1.0`; untuk `Wifi` candidate bisa `0.0` karena mismatched allowed categories.
- Phrase Boost: Tidak. Bergantung pada deteksi frasa runtime dan konten artikel.
- Diversification Score: Ya. Konstan `0.05` sebelum pembobotan.
- Final Score: Tidak. Formula diketahui, tetapi nilai komponen utama bergantung runtime.

## Tabel Komponen

| Komponen | File | Method | Nilai Dapat Diketahui? | Alasan |
|---|---|---|---|---|
| TF-IDF | `app/Services/Chatbot/TfidfService.php` | `calculateTFIDF`, `buildTfidfVectors`, `calculateQueryTFIDF` | Tidak | Memerlukan korpus runtime artikel, frekuensi term dokumen, dan random excerpt duplication. |
| Cosine Similarity | `app/Services/Chatbot/CosineSimilarityService.php` | `calculate` | Tidak | Memerlukan vektor TF-IDF runtime untuk query dan dokumen. |
| Query Coverage | `app/Services/Chatbot/AdvancedRetrievalService.php` | `calculateQueryCoverage` | Tidak | Memerlukan `queryVector` dan `docVector` runtime serta filtering stopword/low-priority. |
| Title Overlap | `app/Services/Chatbot/AdvancedRetrievalService.php` | `calculateTitleOverlap` | Tidak | Nilai bergantung pada token query runtime dan token judul dokumen. |
| Domain Match | `app/Services/Chatbot/AdvancedRetrievalService.php` | `calculateDomainMatch` | Ya (formula) | Formula deterministik: 1.0 jika category cocok allowed categories, 0.0 jika tidak. |
| Phrase Boost | `app/Services/Chatbot/ImportantPhraseService.php` | `getPhraseBoostScore` | Tidak | Tergantung deteksi frasa runtime pada judul, excerpt, dan konten artikel. |
| Diversification Score | `app/Services/Chatbot/AdvancedRetrievalService.php` | `hybridRanking` | Ya | Di-set konstanta `0.05` untuk setiap dokumen sebelum pembobotan. |
| Final Score | `app/Services/Chatbot/AdvancedRetrievalService.php` | `hybridRanking` | Tidak | Formula diketahui, tetapi setiap komponen selain diversifikasi memerlukan nilai runtime. |

## Catatan Penting

- Analisis ini dilakukan tanpa menjalankan sistem.
- Semua angka yang disajikan di atas adalah struktur formula atau nilai konstan yang diinfer dari kode.
- Nilai numerik TF-IDF, Cosine Similarity, dan Phrase Boost tidak diberikan karena pengguna meminta agar tidak mengarang angka.
