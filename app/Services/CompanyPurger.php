<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menghapus seluruh data satu perusahaan tanpa daftar tabel yang ditulis manual.
 * Tabel dibaca dari skema: semua tabel ber-kolom company_id, ditambah tabel anak (mis. detail jurnal,
 * item faktur) yang dicapai lewat foreign key. Dengan begitu tabel baru otomatis ikut terhapus.
 */
class CompanyPurger
{
    /** @var array<string,array> */
    private array $fks = [];
    private array $columns = [];

    public static function purge(int $companyId): void
    {
        (new self())->run($companyId);
    }

    private function cols(string $t): array
    {
        return $this->columns[$t] ??= Schema::getColumnListing($t);
    }

    /** Subquery id baris suatu tabel yang milik perusahaan (langsung via company_id atau lewat tabel induk). */
    private function scope(string $table, string $column, int $companyId, array $visited = [])
    {
        if (in_array($table, $visited, true)) {
            return null;
        }
        $visited[] = $table;

        if ($table === 'companies') {
            return DB::table('companies')->where('id', $companyId)->select($column);
        }
        if (in_array('company_id', $this->cols($table), true)) {
            return DB::table($table)->where('company_id', $companyId)->select($column);
        }
        foreach ($this->fks[$table] ?? [] as $fk) {
            if (count($fk['columns']) !== 1 || $fk['foreign_table'] === $table) {
                continue;
            }
            $parent = $this->scope($fk['foreign_table'], $fk['foreign_columns'][0], $companyId, $visited);
            if ($parent) {
                return DB::table($table)->whereIn($fk['columns'][0], $parent)->select($column);
            }
        }

        return null;
    }

    private function run(int $companyId): void
    {
        $tables = collect(Schema::getTables())->pluck('name')
            ->reject(fn ($t) => in_array($t, ['migrations', 'companies', 'plans', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs']))
            ->values()->all();

        foreach ($tables as $t) {
            $this->fks[$t] = Schema::getForeignKeys($t);
        }

        // tabel yang bisa dibatasi ke perusahaan ini -> [tabel => query pembatas baris]
        $targets = [];
        foreach ($tables as $t) {
            if (in_array('company_id', $this->cols($t), true)) {
                $targets[$t] = fn () => DB::table($t)->where('company_id', $companyId);
                continue;
            }
            // tabel anak: tentukan lewat FK ke tabel yang terbatasi
            foreach ($this->fks[$t] as $fk) {
                if (count($fk['columns']) !== 1 || $fk['foreign_table'] === $t) {
                    continue;
                }
                $parent = $this->scope($fk['foreign_table'], $fk['foreign_columns'][0], $companyId, [$t]);
                if ($parent) {
                    $targets[$t] = fn () => DB::table($t)->whereIn($fk['columns'][0], $parent);
                    break;
                }
            }
        }

        DB::transaction(function () use ($targets, $companyId) {
            // putuskan referensi ke diri sendiri (mis. akun induk) agar urutan hapus tidak mengganjal
            foreach ($targets as $t => $q) {
                foreach ($this->fks[$t] as $fk) {
                    if ($fk['foreign_table'] === $t && count($fk['columns']) === 1) {
                        try { $q()->update([$fk['columns'][0] => null]); } catch (QueryException $e) { /* kolom wajib isi: biarkan */ }
                    }
                }
            }

            // hapus berulang: tabel yang masih dirujuk tabel lain dicoba lagi pada putaran berikutnya
            $pending = array_keys($targets);
            $last = null;
            for ($pass = 0; $pass < 12 && $pending; $pass++) {
                $failed = [];
                foreach ($pending as $t) {
                    try {
                        $targets[$t]()->delete();
                    } catch (QueryException $e) {
                        $failed[$t] = $e;
                    }
                }
                $last = $failed ? end($failed) : null;
                if (count($failed) === count($pending)) {
                    break; // tidak ada kemajuan
                }
                $pending = array_keys($failed);
            }
            if ($pending) {
                throw new \RuntimeException('Tabel ' . implode(', ', $pending) . ' masih terhubung ke data lain: ' . optional($last)->getMessage());
            }

            DB::table('companies')->where('id', $companyId)->delete();
        });
    }
}
