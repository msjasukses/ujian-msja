<?php

namespace App\Support\Template;

/**
 * Daftar template soal yang tersedia.
 *
 * Satu-satunya tempat template didaftarkan. Menambah template baru cukup
 * membuat kelasnya lalu menuliskannya di sini — formulir, validasi, daftar
 * di layar, dan pencariannya menyesuaikan sendiri karena semuanya dibaca
 * dari keterangan yang dinyatakan tiap template.
 */
class Daftar
{
    /** @var array<int, class-string<Template>> */
    public const TEMPLATE = [
        MembilangBenda::class,
        PenjumlahanBergambar::class,
        PembagianBergambar::class,
        PerkalianSusunan::class,
        HitungBersusun::class,
        NilaiTempat::class,
        GarisBilangan::class,
        PolaBilangan::class,
        UangRupiah::class,
        Sudut::class,
        MengenalBentuk::class,
        BangunDatar::class,
        BangunRuang::class,
        JaringJaring::class,
        LingkaranBusur::class,
        Pythagoras::class,
        Kesebangunan::class,
        BidangKoordinat::class,
        TransformasiGeometri::class,
        Vektor::class,
        AljabarBergambar::class,
        Peluang::class,
        AlatUkurDasar::class,
        Pecahan::class,
        JamWaktu::class,
        DiagramData::class,
        UkuranPemusatan::class,
        AritmetikaSosial::class,
        SudutSejajar::class,
        AlatUkurBesaran::class,
        Termometer::class,
        TekananFluida::class,
        GayaGerak::class,
        RangkaianListrik::class,
        PesawatSederhana::class,
        BentukEnergi::class,
        GetaranGelombang::class,
        BumiTataSurya::class,
        LensaBayangan::class,
        PewarisanSifat::class,
        RantaiMakanan::class,
        SiklusHidup::class,
        Simbiosis::class,
        KlasifikasiMakhluk::class,
        GolonganDarah::class,
        SelTranspor::class,
        SistemTubuh::class,
        BagianTumbuhan::class,
        StrukturAtom::class,
        ReaksiStoikiometri::class,
        DiagramEnergi::class,
        WujudZat::class,
        SenyawaKarbon::class,
        SelVolta::class,
        AsamBasa::class,
        SkalaPeta::class,
        GrafikEkonomi::class,
        ArahMataAngin::class,
        PohonKeluarga::class,
        ZonaWaktu::class,
        KoordinatBumi::class,
        PiramidaPenduduk::class,
        ArusEkonomi::class,
        GarisWaktu::class,
        SistemBilangan::class,
        GerbangLogika::class,
        LembarKerja::class,
        DiagramAlir::class,
        TopologiJaringan::class,
        PerangkatKomputer::class,
    ];

    /** @return array<string, class-string<Template>> */
    public static function semua(): array
    {
        static $daftar = null;

        return $daftar ??= collect(self::TEMPLATE)
            ->mapWithKeys(fn (string $kelas) => [$kelas::kode() => $kelas])
            ->all();
    }

    public static function ada(string $kode): bool
    {
        return isset(self::semua()[$kode]);
    }

    /** @return class-string<Template> */
    public static function kelas(string $kode): string
    {
        return self::semua()[$kode];
    }

    public static function buat(string $kode): Template
    {
        $kelas = self::kelas($kode);

        return new $kelas;
    }

    /**
     * Keterangan seluruh template untuk ditampilkan peramban, dikelompokkan
     * menurut mata pelajarannya seperti yang tampil di daftar.
     *
     * @return array<int, array{mapel: string, template: array<int, array<string, mixed>>}>
     */
    public static function katalog(): array
    {
        $kelompok = [];

        foreach (self::semua() as $kelas) {
            $kelompok[$kelas::mapel()][] = $kelas::katalog();
        }

        return array_map(
            fn ($mapel, $template) => ['mapel' => $mapel, 'template' => $template],
            array_keys($kelompok),
            $kelompok,
        );
    }
}
