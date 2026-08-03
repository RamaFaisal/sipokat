# Rencana Diagram UML — Sipokat

Sistem: **Sipokat — Sistem Inventory Obat + SPK SAW (Apotek Anugrah Husada)**

Dokumen ini memuat **kode Mermaid** untuk setiap diagram yang dibutuhkan pada Bab III, dengan penomoran gambar yang sama seperti pada `draft_isi_ta.md`. Class Diagram tidak digunakan karena struktur data diwakili ERD.

**Cara pakai:** render di VS Code (ekstensi Markdown Preview Mermaid) atau di [mermaid.live](https://mermaid.live), lalu ekspor PNG/SVG dan sisipkan ke naskah pada penanda `【Sisipkan gambar di sini】`.

## Aktor

| Aktor | Peran | Ringkasan hak akses |
|-------|-------|---------------------|
| **Admin** | Apoteker Penanggung Jawab | Master data, konfigurasi kriteria & bobot SAW, jalankan perhitungan SAW, laporan, manajemen user |
| **Petugas** | Staf Gudang | Obat masuk (Receive Order), obat keluar (penjualan), stok opname, lihat stok |
| **Pemilik** | Manajer / Pemilik | Dashboard, laporan rekap, hasil perangkingan SAW (umumnya read-only) |

## Daftar gambar

| Gambar | Judul | Prioritas |
|---|---|---|
| 3.2 | Use Case Diagram Sistem | Wajib |
| 3.3 | Activity Diagram Login (F-01) | Wajib |
| 3.4 | Activity Diagram Pencatatan Obat Masuk (F-03) | Wajib |
| 3.5 | Activity Diagram Pencatatan Obat Keluar (F-03) | Wajib |
| 3.6 | Activity Diagram Perhitungan Prioritas Restock (F-06/F-07) | **Wajib (inti)** |
| 3.7 | Entity Relationship Diagram (ERD) | Wajib |

---

## Gambar 3.2 — Use Case Diagram Sistem

Use case mengikuti persis 7 kebutuhan fungsional Tabel 3.3 (F-01–F-07) di `draft_isi_ta.md` — tidak ditambah use case lain di luar yang tertulis di naskah. Pemetaan aktor mengikuti kolom "Kebutuhan Fitur dan Fungsi" pada Tabel 3.2.

| Kode | Use Case | Admin | Petugas | Pemilik |
|---|----------|:---:|:---:|:---:|
| F-01 | Login | ✅ | ✅ | ✅ |
| F-02 | Kelola Data Obat | ✅ | | |
| F-03 | Catat Obat Masuk & Keluar | ✅ | ✅ | |
| F-04 | Notifikasi Stok Minimum & Kedaluwarsa | ✅ | ✅ | |
| F-05 | Lihat Laporan Inventory | ✅ | | ✅ |
| F-06 | Hitung Prioritas Restock (SAW) | ✅ | ✅ | |
| F-07 | Lihat Hasil Perangkingan | ✅ | ✅ | ✅ |

> **Catatan render:** mengikuti gaya diagram use case klasik (aktor di kiri/kanan, use case dalam satu kolom vertikal di dalam kotak subsistem). Semua use case sengaja **tidak** dihubungkan satu sama lain — hanya diberi edge dari/ke aktor — supaya Mermaid menempatkan mereka pada rank yang sama (satu kolom) dan menyusunnya vertikal secara otomatis. Jangan tambahkan link berantai antar use case (termasuk invisible link `~~~`): pada `flowchart LR`, rank berjalan horizontal, jadi link semacam itu justru mendorong tiap node ke kolom berikutnya alih-alih menyusunnya vertikal.

```mermaid
---
config:
  layout: fixed
---
flowchart LR
    admin(("👤 Admin"))
    petugas(("👤 Petugas"))
    pemilik(("👤 Pemilik"))

    subgraph SIP["Sistem Sipokat"]
        L(["Login"])
        OBT(["Kelola Data Obat"])
        TRX(["Catat Obat Masuk<br/>& Keluar"])
        NOT(["Notifikasi Stok Minimum<br/>& Kedaluwarsa"])
        LAP(["Lihat Laporan<br/>Inventory"])
        SAW(["Hitung Prioritas<br/>Restock (SAW)"])
        RNK(["Lihat Hasil<br/>Perangkingan"])
    end

    admin --- L & OBT & TRX & NOT & LAP & SAW & RNK
    petugas --- L & TRX & NOT & SAW & RNK
    L & LAP & RNK --- pemilik

    classDef actor fill:#ffffff,stroke:#000000,stroke-width:1.5px,color:#000000;
    classDef usecase fill:#ffffff,stroke:#000000,stroke-width:1px,color:#000000;
    class admin,petugas,pemilik actor;
    class L,OBT,TRX,NOT,LAP,SAW,RNK usecase;
    style SIP fill:#ffffff,stroke:#000000,stroke-width:1px;
```

---

## Gambar 3.3 — Activity Diagram Login (F-01)

```mermaid
---
config:
  layout: fixed
---
flowchart TB
 subgraph s1[" "]
        A((" "))
        B["Akses Website"]
        C["Masukkan Username dan Password"]
        D{"Verifikasi"}
        E["Masuk Ke Dashboard"]
        F((" "))
        G["Notifikasi"]
  end
    A --> B
    B --> C
    C L_C_D_0@--> D
    E --> F
    D L_D_G_0@-- Tidak --> G
    G --> C
    D L_D_E_0@-- Ya --> E

    style A fill:#000000
    style D fill:#FFD600
    style F fill:#000000

    L_C_D_0@{ curve: linear } 
    L_D_G_0@{ curve: linear } 
    L_D_E_0@{ curve: linear }
```

---

## Gambar 3.4 — Activity Diagram Pencatatan Obat Masuk (F-03)

```mermaid
---
config:
  layout: fixed
---
flowchart TB
 subgraph s1[" "]
        A((" "))
        B["Membuka Menu Obat Masuk"]
        C["Memilih Supplier dan Data Pesanan"]
        D["Memasukkan Obat dan Jumlah yang Diterima"]
        E["Memasukkan Nomor Batch, Tanggal Produksi, dan Tanggal Kedaluwarsa"]
        F{"Data Lengkap dan Valid?"}
        G["Menampilkan Pesan Kesalahan"]
        H["Menyimpan Transaksi Obat Masuk"]
        I["Menambah Stok Obat"]
        J["Memperbarui Status Stok Obat"]
        K((" "))
  end
    A --> B
    B --> C
    C --> D
    D --> E
    E L_E_F_0@--> F
    F L_F_G_0@-- Tidak --> G
    G --> D
    F L_F_H_0@-- Ya --> H
    H --> I
    I --> J
    J --> K

    style A fill:#000000
    style F fill:#FFD600
    style K fill:#000000

    L_E_F_0@{ curve: linear }
    L_F_G_0@{ curve: linear }
    L_F_H_0@{ curve: linear }
```

---

## Gambar 3.5 — Activity Diagram Pencatatan Obat Keluar (F-03)

```mermaid
---
config:
  layout: fixed
---
flowchart TB
 subgraph s1[" "]
        A((" "))
        B["Membuka Menu Penjualan"]
        C["Memilih Obat dan Memasukkan Jumlah"]
        D["Memeriksa Ketersediaan Stok"]
        E{"Jumlah Kurang dari atau Sama dengan Stok?"}
        F["Menampilkan Pesan Stok Tidak Mencukupi"]
        G["Menyimpan Transaksi Obat Keluar"]
        H["Mengurangi Stok Obat"]
        I["Memperbarui Status Stok Obat"]
        J((" "))
  end
    A --> B
    B --> C
    C --> D
    D L_D_E_0@--> E
    E L_E_F_0@-- Tidak --> F
    F --> C
    E L_E_G_0@-- Ya --> G
    G --> H
    H --> I
    I --> J

    style A fill:#000000
    style E fill:#FFD600
    style J fill:#000000

    L_D_E_0@{ curve: linear }
    L_E_F_0@{ curve: linear }
    L_E_G_0@{ curve: linear }
```

---

## Gambar 3.6 — Activity Diagram Perhitungan Prioritas Restock (SAW) (F-06/F-07) **(inti)**

```mermaid
---
config:
  layout: fixed
---
flowchart TB
 subgraph s1[" "]
        A((" "))
        B["Membuka Halaman Hitung Prioritas Restock"]
        C["Menentukan Periode Perhitungan"]
        D["Mengambil Kriteria dan Bobot yang Aktif"]
        E{"Total Bobot Sama dengan 1?"}
        F["Menampilkan Peringatan Bobot Tidak Valid"]
        G["Mengambil Data Obat Aktif"]
        H["Menghitung Nilai Mentah Kriteria C1 sampai C4"]
        I["Mengonversi Nilai Mentah ke Skala 1 sampai 5"]
        J["Normalisasi Matriks (Cost: Min/X, Benefit: X/Max)"]
        K["Menghitung Nilai Preferensi Vi = ΣWj × Rij"]
        L["Mengurutkan Obat Berdasarkan Vi Menurun"]
        M["Menyimpan Hasil Perhitungan"]
        N["Menampilkan Tabel Perangkingan Prioritas Restock"]
        Z((" "))
  end
    A --> B
    B --> C
    C --> D
    D --> E
    E L_E_F_0@-- Tidak --> F
    F --> Z
    E L_E_G_0@-- Ya --> G
    G --> H
    H --> I
    I --> J
    J --> K
    K --> L
    L --> M
    M --> N
    N --> Z

    style A fill:#000000
    style E fill:#FFD600
    style Z fill:#000000
    style H stroke-width:3px
    style I stroke-width:3px
    style J stroke-width:3px
    style K stroke-width:3px
    style L stroke-width:3px

    L_E_F_0@{ curve: linear }
    L_E_G_0@{ curve: linear }
```

---

## Gambar 3.7 — Entity Relationship Diagram (ERD)

```mermaid
erDiagram
    USERS {
        int id PK
        string name
        string email
        string password
    }
    MEDICINE_CATEGORIES {
        int id PK
        string name
    }
    MEDICINE_RACKS {
        int id PK
        string name
    }
    UNITS {
        int id PK
        string name
    }
    SUPPLIERS {
        int id PK
        string name
        string phone
    }
    MEDICINES {
        int id PK
        string code
        string name
        int category_id FK
        int rack_id FK
        int unit_id FK
        decimal purchase_price
        decimal selling_price
        int min_stock
    }
    PURCHASE_ORDERS {
        int id PK
        int supplier_id FK
        date po_date
        string status
    }
    PURCHASE_ORDER_ITEMS {
        int id PK
        int purchase_order_id FK
        int medicine_id FK
        int qty
    }
    RECEIVE_ORDERS {
        int id PK
        int purchase_order_id FK
        int received_by FK
        date receive_date
    }
    RECEIVE_ORDER_ITEMS {
        int id PK
        int receive_order_id FK
        int medicine_id FK
        int qty
        string batch_number
        date expired_date
    }
    ORDERS {
        int id PK
        int created_by FK
        date order_date
        string status
    }
    ORDER_ITEMS {
        int id PK
        int order_id FK
        int medicine_id FK
        int qty
    }
    MEDICINE_STOCKS {
        int id PK
        int medicine_id FK
        string type
        int qty
        date date
    }
    SAW_CRITERIA {
        int id PK
        string code
        string name
        string type
        decimal weight
        json scale_rules
    }
    SAW_CALCULATIONS {
        int id PK
        int calculated_by FK
        date period_start
        date period_end
        json criteria_snapshot
    }
    SAW_CALCULATION_RESULTS {
        int id PK
        int saw_calculation_id FK
        int medicine_id FK
        decimal preference_value
        int rank
    }

    MEDICINE_CATEGORIES ||--o{ MEDICINES : mengelompokkan
    MEDICINE_RACKS ||--o{ MEDICINES : menyimpan
    UNITS ||--o{ MEDICINES : menyatakan

    SUPPLIERS ||--o{ PURCHASE_ORDERS : memasok
    PURCHASE_ORDERS ||--o{ PURCHASE_ORDER_ITEMS : memiliki
    MEDICINES ||--o{ PURCHASE_ORDER_ITEMS : dipesan
    PURCHASE_ORDERS ||--o{ RECEIVE_ORDERS : ditindaklanjuti
    RECEIVE_ORDERS ||--o{ RECEIVE_ORDER_ITEMS : memiliki
    MEDICINES ||--o{ RECEIVE_ORDER_ITEMS : diterima

    ORDERS ||--o{ ORDER_ITEMS : memiliki
    MEDICINES ||--o{ ORDER_ITEMS : dijual
    MEDICINES ||--o{ MEDICINE_STOCKS : mencatat

    USERS ||--o{ RECEIVE_ORDERS : menerima
    USERS ||--o{ ORDERS : mencatat
    USERS ||--o{ SAW_CALCULATIONS : menjalankan

    SAW_CRITERIA ||--o{ SAW_CALCULATIONS : mendasari
    SAW_CALCULATIONS ||--o{ SAW_CALCULATION_RESULTS : menghasilkan
    MEDICINES ||--o{ SAW_CALCULATION_RESULTS : dinilai
```

> **Catatan ERD:** entitas ditampilkan dengan atribut inti saja agar diagram tetap terbaca. Bila pembimbing meminta ERD lengkap, gunakan versi lengkap di bawah ini.

---

## Lampiran — ERD Lengkap *(opsional, seluruh atribut sesuai struktur tabel database)*

Dibangun langsung dari struktur migration aktual (18 tabel domain bisnis). Tabel infrastruktur framework (cache, jobs, sessions, permission/roles Spatie, notifications, settings, imports/exports) tidak diikutkan karena bukan bagian dari ERD bisnis inti. Gunakan versi ini kalau pembimbing minta detail penuh; kalau tidak, cukup pakai Gambar 3.7 di atas.

```mermaid
erDiagram
    USERS {
        int id PK
        string name
        string email UK
        timestamp email_verified_at
        string password
        string remember_token
        timestamp created_at
        timestamp updated_at
    }
    UNITS {
        int id PK
        string name
        string alias
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }
    MEDICINE_CATEGORIES {
        int id PK
        string name
        string alias
        string description
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }
    MEDICINE_RACKS {
        int id PK
        string name
        string description
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }
    SUPPLIERS {
        int id PK
        string code UK
        string name
        string address
        string pic
        string phone
        string email
        string status
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }
    MEDICINES {
        int id PK
        string code UK
        string name
        string dosage
        int category_id FK
        int unit_id FK
        int rack_id FK
        string photo
        decimal purchase_price
        decimal sale_price
        int min_stock
        string stock_status
        string status
        string description
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }
    PURCHASE_ORDERS {
        int id PK
        string po_number UK
        int supplier_id FK
        date po_date
        decimal sub_total
        decimal discount
        decimal tax
        decimal total_tax
        decimal shipping_cost
        decimal other_cost
        decimal grand_total
        string status
        string description
        date estimated_arrival
        string status_payment
        string status_receive_order
        int created_by FK
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }
    PURCHASE_ORDER_ITEMS {
        int id PK
        int purchase_order_id FK
        int medicine_id FK
        string description
        int qty
        decimal price
        double discount
        decimal total
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }
    RECEIVE_ORDERS {
        int id PK
        string receive_order_number UK
        int purchase_order_id FK
        int supplier_id FK
        date receive_date
        string description
        string status
        boolean late_arrival
        int received_by FK
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }
    RECEIVE_ORDER_ITEMS {
        int id PK
        int receive_order_id FK
        int medicine_id FK
        string medicine_name
        int qty
        decimal price
        string batch_number
        date manufacture_date
        date expired_date
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }
    MEDICINE_STOCKS {
        int id PK
        int medicine_id FK
        int qty
        string type_account
        date date
        int receive_order_id FK
        int medicine_stock_opname_id FK
        int order_id FK
        decimal hpp
        string description
        int created_by FK
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }
    MEDICINE_STOCK_OPNAMES {
        int id PK
        string opname_number UK
        date opname_date
        string status
        string description
        int created_by FK
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }
    MEDICINE_STOCK_OPNAME_ITEMS {
        int id PK
        int medicine_stock_opname_id FK
        int medicine_id FK
        int qty
        decimal hpp
        string type_account
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }
    ORDERS {
        int id PK
        string order_code UK
        string no_payment UK
        date order_date
        decimal grand_total
        string status
        string note
        int created_by FK
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }
    ORDER_ITEMS {
        int id PK
        int order_id FK
        int medicine_id FK
        string medicine_name
        int qty
        decimal price
        decimal total
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }
    SAW_CRITERIA {
        int id PK
        string code UK
        string name
        string type
        decimal weight
        json scale_rules
        string description
        int sort_order
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }
    SAW_CALCULATIONS {
        int id PK
        timestamp calculated_at
        int calculated_by FK
        date period_start
        date period_end
        string trigger_type
        json criteria_snapshot
        int total_alternatives
        string notes
        timestamp created_at
        timestamp updated_at
    }
    SAW_CALCULATION_RESULTS {
        int id PK
        int saw_calculation_id FK
        int medicine_id FK
        decimal c1_raw
        decimal c2_raw
        decimal c3_raw
        decimal c4_raw
        int c1_score
        int c2_score
        int c3_score
        int c4_score
        decimal c1_norm
        decimal c2_norm
        decimal c3_norm
        decimal c4_norm
        decimal preference_value
        int rank
        timestamp created_at
        timestamp updated_at
    }

    MEDICINE_CATEGORIES ||--o{ MEDICINES : mengelompokkan
    MEDICINE_RACKS ||--o{ MEDICINES : menyimpan
    UNITS ||--o{ MEDICINES : menyatakan

    SUPPLIERS ||--o{ PURCHASE_ORDERS : memasok
    USERS ||--o{ PURCHASE_ORDERS : membuat
    PURCHASE_ORDERS ||--o{ PURCHASE_ORDER_ITEMS : memiliki
    MEDICINES ||--o{ PURCHASE_ORDER_ITEMS : dipesan

    PURCHASE_ORDERS ||--o{ RECEIVE_ORDERS : ditindaklanjuti
    SUPPLIERS ||--o{ RECEIVE_ORDERS : memasok
    USERS ||--o{ RECEIVE_ORDERS : menerima
    RECEIVE_ORDERS ||--o{ RECEIVE_ORDER_ITEMS : memiliki
    MEDICINES ||--o{ RECEIVE_ORDER_ITEMS : diterima

    USERS ||--o{ ORDERS : mencatat
    ORDERS ||--o{ ORDER_ITEMS : memiliki
    MEDICINES ||--o{ ORDER_ITEMS : dijual

    USERS ||--o{ MEDICINE_STOCK_OPNAMES : membuat
    MEDICINE_STOCK_OPNAMES ||--o{ MEDICINE_STOCK_OPNAME_ITEMS : memiliki
    MEDICINES ||--o{ MEDICINE_STOCK_OPNAME_ITEMS : disesuaikan

    MEDICINES ||--o{ MEDICINE_STOCKS : mencatat
    USERS ||--o{ MEDICINE_STOCKS : mencatat
    RECEIVE_ORDERS ||--o{ MEDICINE_STOCKS : menambah
    ORDERS ||--o{ MEDICINE_STOCKS : mengurangi
    MEDICINE_STOCK_OPNAMES ||--o{ MEDICINE_STOCKS : menyesuaikan

    USERS ||--o{ SAW_CALCULATIONS : menjalankan
    SAW_CRITERIA ||--o{ SAW_CALCULATIONS : mendasari
    SAW_CALCULATIONS ||--o{ SAW_CALCULATION_RESULTS : menghasilkan
    MEDICINES ||--o{ SAW_CALCULATION_RESULTS : dinilai
```
