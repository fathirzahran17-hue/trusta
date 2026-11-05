<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\CoretaxFiscalRow;
use App\Models\Journal;
use App\Models\User;
use App\Services\Coretax\CoretaxCatalog;
use App\Services\Coretax\CoretaxMapper;
use App\Services\Coretax\CoretaxReconciliation;
use App\Services\CoaTemplate;
use App\Services\EarningsLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pemetaan akun ke Lampiran 1 SPT Badan, kecocokan angkanya dengan pembukuan, dan kolom fiskalnya. */
class CoretaxTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private int $cid;
    private int $year;

    protected function setUp(): void
    {
        parent::setUp();
        $this->year = (int) date('Y');
        $this->user = $this->registerCompany('PT Coretax', 'coretax@uji.test', 'manufaktur');
        $this->actingAs($this->user);
        $this->cid = (int) $this->user->company_id;
    }

    private function registerCompany(string $name, string $email, string $type): User
    {
        auth()->logout();
        $this->post('/register', ['company_name' => $name, 'name' => 'Owner', 'email' => $email, 'password' => 'Rahasia#2026xQ', 'password_confirmation' => 'Rahasia#2026xQ',
            'business_type' => $type, 'terms' => '1']);
        $user = User::where('email', $email)->firstOrFail();
        $user->company->forceFill(['is_premium' => true, 'business_type' => $type])->save();
        auth()->logout();

        return $user;
    }

    private function acc(string $name): Account
    {
        return Account::where('company_id', $this->cid)->where('name', $name)->firstOrFail();
    }

    /** @param array<int,array{0:string,1:float,2:float}> $lines nama akun, debit, kredit */
    private function journal(string $date, array $lines): void
    {
        $journal = Journal::create(['company_id' => $this->cid, 'ref_number' => 'J-' . uniqid(), 'type' => 'general', 'transaction_date' => $date, 'description' => 'uji', 'status' => 'posted', 'created_by' => $this->user->id]);
        foreach ($lines as [$name, $debit, $credit]) {
            $journal->details()->create(['account_id' => $this->acc($name)->id, 'debit' => $debit, 'credit' => $credit]);
        }
    }

    private function rec(string $lampiran): CoretaxReconciliation
    {
        EarningsLink::flush();

        return (new CoretaxReconciliation($this->cid, $this->year, $lampiran))->build();
    }

    public function test_every_default_account_gets_a_valid_code_for_every_form(): void
    {
        $accounts = collect(CoaTemplate::standard())->map(fn ($row) => new Account(['code' => $row[0], 'name' => $row[1], 'type' => $row[2], 'normal_balance' => $row[3]]));
        $byHeader = collect(CoaTemplate::standard())->filter(fn ($row) => $row[5]);
        $checked = 0;
        foreach (array_keys(CoretaxCatalog::LAMPIRAN) as $lampiran) {
            $mapper = new CoretaxMapper([], $lampiran);
            foreach ($accounts as $i => $account) {
                if ($byHeader->has($i)) {
                    continue;
                }
                $result = $mapper->resolve($account);
                $this->assertTrue(CoretaxCatalog::isChoice($result['code'], $account->type, $lampiran), "{$account->name} -> {$result['code']} ({$lampiran})");
                $checked++;
            }
        }
        $this->assertGreaterThan(200, $checked);
    }

    public function test_catalog_totals_only_refer_to_known_rows_defined_earlier(): void
    {
        foreach (array_keys(CoretaxCatalog::LAMPIRAN) as $lampiran) {
            foreach ([CoretaxCatalog::profitLoss($lampiran), CoretaxCatalog::position()] as $rows) {
                $seen = [];
                foreach ($rows as $row) {
                    foreach ($row['sum'] ?? [] as $op) {
                        $this->assertArrayHasKey(substr($op, 1), $seen, "{$lampiran}: {$row['code']} memakai {$op} sebelum didefinisikan");
                    }
                    if (isset($row['code'])) {
                        $this->assertArrayNotHasKey($row['code'], $seen, "{$lampiran}: kode {$row['code']} dobel");
                        $seen[$row['code']] = true;
                    }
                }
            }
        }
    }

    public function test_common_accounts_land_on_the_expected_lines(): void
    {
        $expected = [
            ['Kas', '1101'], ['Piutang Usaha', '1122'], ['Piutang Karyawan', '1124'], ['Cadangan Kerugian Piutang', '1131'], ['Persediaan Bahan Baku', '1401'],
            ['Pajak Masukan (PPN)', '1423'], ['Uang Muka Pembelian', '1422'], ['Sewa Dibayar Dimuka', '1421'], ['Tanah', '1523'], ['Akumulasi Penyusutan Bangunan', '1524'],
            ['Akumulasi Penyusutan Mesin', '1530'], ['Mesin & Peralatan Produksi', '1529'], ['Uang Jaminan', '1698'], ['Hutang Usaha', '2102'], ['Hutang PPh 21', '2191'],
            ['Hutang Gaji & Upah', '2195'], ['Uang Muka Penjualan', '2203'], ['Hutang Bank', '2301'], ['Hutang Leasing', '2312'], ['Hutang Pemegang Saham', '2304'],
            ['Modal Disetor', '3102'], ['Laba Ditahan', '3200'], ['Prive Pemilik', '3298'], ['Beban Gaji & Upah', '5311'], ['Beban BPJS', '5312'], ['Beban Sewa', '5315'],
            ['Beban Penyusutan', '5314'], ['Beban Jasa Profesional', '5317'], ['Beban Iklan & Promosi', '5320'], ['Beban Listrik, Air & Telepon', '5322'], ['Pendapatan Bunga Bank', '4511'],
            ['Laba Selisih Kurs', '4501'], ['Rugi Pelepasan Aset Tetap', '5405'], ['Beban PPh Badan', '5399'],
        ];
        $mapper = new CoretaxMapper(Account::where('company_id', $this->cid)->get(), '1C');
        foreach ($expected as [$name, $code]) {
            $this->assertSame($code, $mapper->resolve($this->acc($name))['code'], $name);
        }
    }

    public function test_trading_form_ties_to_books_and_derives_purchases(): void
    {
        $date = $this->year . '-03-10';
        // Beli 1.000.000, jual seharga 900.000 dengan HPP 600.000, ditambah beban dan penyusutan
        $this->journal($date, [['Persediaan Bahan Baku', 1000000, 0], ['Hutang Usaha', 0, 1000000]]);
        $this->journal($date, [['Piutang Usaha', 900000, 0], ['Pendapatan Penjualan', 0, 900000]]);
        $this->journal($date, [['HPP - Barang Jadi', 600000, 0], ['Persediaan Bahan Baku', 0, 600000]]);
        $this->journal($date, [['Retur Penjualan', 50000, 0], ['Piutang Usaha', 0, 50000]]);
        $this->journal($date, [['Beban Sewa', 100000, 0], ['Kas', 0, 100000]]);
        $this->journal($date, [['Beban Penyusutan', 20000, 0], ['Akumulasi Penyusutan Mesin', 0, 20000]]);

        $rec = $this->rec('1C');

        $this->assertEquals(900000, $rec->value('4002'));
        $this->assertEquals(50000, $rec->value('4011'));
        $this->assertEquals(850000, $rec->value('4020'));
        $this->assertEquals(0, $rec->value('5008'));
        $this->assertEquals(400000, $rec->value('5009'));
        $this->assertEquals(1000000, $rec->value('5001'));
        $this->assertEquals(600000, $rec->value('5020'));
        $this->assertEquals(120000, $rec->value('5400'));
        $this->assertEquals(EarningsLink::profit($this->cid, $rec->start, $rec->end), $rec->value('4800'));
        $this->assertEquals(130000, $rec->value('4800'));
        $this->assertEquals(-20000, $rec->value('1530'));
        $this->assertEquals($rec->value('1700'), $rec->value('3300'));
        $this->assertEquals(400000, $rec->value('1401'));
    }

    public function test_opening_inventory_moves_into_the_cost_formula(): void
    {
        $this->journal(($this->year - 1) . '-06-01', [['Persediaan Bahan Baku', 300000, 0], ['Modal Disetor', 0, 300000]]);
        $date = $this->year . '-02-01';
        $this->journal($date, [['Persediaan Bahan Baku', 500000, 0], ['Kas', 0, 500000]]);
        $this->journal($date, [['HPP - Barang Jadi', 700000, 0], ['Persediaan Bahan Baku', 0, 700000]]);

        $rec = $this->rec('1C');

        $this->assertEquals(300000, $rec->value('5008'));
        $this->assertEquals(100000, $rec->value('5009'));
        $this->assertEquals(500000, $rec->value('5001'));
        $this->assertEquals(700000, $rec->value('5020'));
    }

    public function test_manufacturing_form_recovers_material_purchases_and_ties_to_books(): void
    {
        $date = $this->year . '-04-01';
        // Bahan 1.000.000, dipakai 800.000, tenaga kerja 100.000 dan overhead 50.000 diserap ke barang dalam proses, lalu barang jadi 950.000, terjual 570.000
        $this->journal($date, [['Persediaan Bahan Baku', 1000000, 0], ['Kas', 0, 1000000]]);
        $this->journal($date, [['Persediaan Barang Dalam Proses', 800000, 0], ['Persediaan Bahan Baku', 0, 800000]]);
        $this->journal($date, [['Biaya Tenaga Kerja Langsung', 100000, 0], ['Kas', 0, 100000]]);
        $this->journal($date, [['Biaya Overhead Pabrik', 50000, 0], ['Kas', 0, 50000]]);
        $this->journal($date, [['Persediaan Barang Dalam Proses', 150000, 0], ['Biaya Tenaga Kerja Dibebankan ke Produksi', 0, 100000], ['Overhead Pabrik Dibebankan ke Produksi', 0, 50000]]);
        $this->journal($date, [['Persediaan Barang Jadi', 950000, 0], ['Persediaan Barang Dalam Proses', 0, 950000]]);
        $this->journal($date, [['HPP - Barang Jadi', 570000, 0], ['Persediaan Barang Jadi', 0, 570000]]);
        $this->journal($date, [['Kas', 1000000, 0], ['Pendapatan Penjualan', 0, 1000000]]);

        $rec = $this->rec('1B');

        $this->assertEquals(1000000, $rec->value('5022'));
        $this->assertEquals(200000, $rec->value('5032'));
        $this->assertEquals(800000, $rec->value('5040'));
        $this->assertEquals(100000, $rec->value('5050'));
        $this->assertEquals(50000, $rec->value('5069'));
        $this->assertEquals(950000, $rec->value('5080'));
        $this->assertEquals(950000, $rec->value('5100'));
        $this->assertEquals(380000, $rec->value('5009'));
        $this->assertEquals(570000, $rec->value('5020'));
        $this->assertEquals(430000, $rec->value('4800'));
        $this->assertEquals(EarningsLink::profit($this->cid, $rec->start, $rec->end), $rec->value('4800'));
        $this->assertEquals($rec->value('1700'), $rec->value('3300'));
    }

    public function test_service_form_ties_to_books_and_has_no_general_expense_line(): void
    {
        $date = $this->year . '-05-05';
        Account::create(['company_id' => $this->cid, 'code' => '4-1902', 'name' => 'Pendapatan Jasa', 'type' => 'revenue', 'normal_balance' => 'credit', 'parent_id' => $this->acc('Pendapatan Penjualan')->parent_id]);
        $this->journal($date, [['Kas', 500000, 0], ['Pendapatan Jasa', 0, 500000]]);
        $this->journal($date, [['Beban Listrik, Air & Telepon', 30000, 0], ['Kas', 0, 30000]]);

        $rec = $this->rec('1D');

        $this->assertEquals(500000, $rec->value('4021'));
        $this->assertEquals(30000, $rec->value('5399'));
        $this->assertEquals(0, $rec->value('5322'));
        $this->assertEquals(470000, $rec->value('4800'));
        $this->assertEquals($rec->value('1700'), $rec->value('3300'));
    }

    public function test_manual_mapping_wins_and_a_code_from_another_form_falls_back_to_auto(): void
    {
        $rent = $this->acc('Beban Sewa');
        $this->journal($this->year . '-01-15', [['Beban Sewa', 75000, 0], ['Kas', 0, 75000]]);

        $this->post(route('client.reports.coretax.mapping.save', ['lampiran' => '1C', 'tahun' => $this->year]), ['codes' => [$rent->id => '5322']])->assertRedirect();
        $this->assertSame('5322', $rent->fresh()->coretax_code);

        $rec = $this->rec('1C');
        $this->assertEquals(75000, $rec->value('5322'));
        $this->assertEquals(0, $rec->value('5315'));

        // Kode 5322 tidak ada di Lampiran 1D, jadi kembali ke saran otomatis
        $this->assertEquals(75000, $this->rec('1D')->value('5315'));

        $this->post(route('client.reports.coretax.mapping.save', ['lampiran' => '1C', 'tahun' => $this->year]), ['codes' => [$rent->id => '']])->assertRedirect();
        $this->assertNull($rent->fresh()->coretax_code);
    }

    public function test_mapping_rejects_a_code_of_the_wrong_side(): void
    {
        $rent = $this->acc('Beban Sewa');
        $this->post(route('client.reports.coretax.mapping.save', ['lampiran' => '1C', 'tahun' => $this->year]), ['codes' => [$rent->id => '1101']])
            ->assertSessionHasErrors("codes.{$rent->id}");
        $this->assertNull($rent->fresh()->coretax_code);
    }

    public function test_mapping_cannot_touch_another_company(): void
    {
        $other = $this->registerCompany('PT Lain', 'lain@uji.test', 'dagang');
        $this->actingAs($this->user);
        $foreign = Account::withoutGlobalScopes()->where('company_id', $other->company_id)->where('name', 'Beban Sewa')->firstOrFail();

        $this->post(route('client.reports.coretax.mapping.save', ['lampiran' => '1C', 'tahun' => $this->year]), ['codes' => [$foreign->id => '5322']])->assertRedirect();

        $this->assertNull(Account::withoutGlobalScopes()->find($foreign->id)->coretax_code);
    }

    public function test_fiscal_columns_follow_the_form_rules(): void
    {
        $date = $this->year . '-07-01';
        $this->journal($date, [['Kas', 1000000, 0], ['Pendapatan Penjualan', 0, 1000000]]);
        $this->journal($date, [['Kas', 200000, 0], ['Pendapatan Bunga Bank', 0, 200000]]);
        $this->journal($date, [['Beban Sewa', 300000, 0], ['Kas', 0, 300000]]);

        $response = $this->post(route('client.reports.coretax.fiscal.save', ['lampiran' => '1C', 'tahun' => $this->year]), ['rows' => [
            '4511' => ['final_income' => '200.000,00', 'negative' => '', 'positive' => '', 'non_object' => ''],
            '5315' => ['positive' => '50.000,00', 'adjustment_codes' => 'fpo-12'],
        ]]);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $rec = $this->rec('1C');
        $this->assertEquals(200000, $rec->value('4511', 'final'));
        $this->assertEquals(0, $rec->value('4511', 'object'));
        $this->assertEquals(0, $rec->value('4511', 'fiscal'));
        $this->assertEquals(50000, $rec->value('5315', 'positive'));
        $this->assertSame('FPO-12', $rec->values['5315']['codes']);
        $this->assertEquals(250000, $rec->value('5315', 'fiscal'));
        $this->assertEquals(900000, $rec->value('4800'));
        // 900.000 komersial - 200.000 final + 50.000 koreksi positif
        $this->assertEquals(750000, $rec->value('4800', 'fiscal'));
        $this->assertEquals(50000, $rec->value('4800', 'positive'));
        $this->assertEquals(200000, $rec->value('4800', 'final'));
    }

    public function test_fiscal_rows_need_a_matching_adjustment_code(): void
    {
        $url = route('client.reports.coretax.fiscal.save', ['lampiran' => '1C', 'tahun' => $this->year]);
        $this->post($url, ['rows' => ['5315' => ['positive' => '1000']]])->assertSessionHasErrors('rows.5315.adjustment_codes');
        $this->post($url, ['rows' => ['5315' => ['positive' => '1000', 'adjustment_codes' => 'FNE-01']]])->assertSessionHasErrors('rows.5315.adjustment_codes');
        $this->post($url, ['rows' => ['5315' => ['negative' => '1000', 'adjustment_codes' => 'ABC-99']]])->assertSessionHasErrors('rows.5315.adjustment_codes');
        $this->assertSame(0, CoretaxFiscalRow::count());

        $this->post($url, ['rows' => ['5315' => ['positive' => '1000', 'negative' => '500', 'adjustment_codes' => 'FPO-01, FNE-04']]])->assertSessionHasNoErrors();
        $this->assertSame('FPO-01, FNE-04', CoretaxFiscalRow::first()->adjustment_codes);

        // Dikosongkan kembali, barisnya hilang
        $this->post($url, ['rows' => ['5315' => ['positive' => '', 'negative' => '', 'adjustment_codes' => '']]])->assertSessionHasNoErrors();
        $this->assertSame(0, CoretaxFiscalRow::count());
    }

    public function test_expense_rows_ignore_income_only_columns(): void
    {
        $this->post(route('client.reports.coretax.fiscal.save', ['lampiran' => '1C', 'tahun' => $this->year]), ['rows' => ['5315' => ['non_object' => '1000', 'final_income' => '1000']]]);
        $this->assertSame(0, CoretaxFiscalRow::count());
    }

    public function test_pages_and_exports_work_and_staff_is_read_only(): void
    {
        $date = $this->year . '-08-01';
        $this->journal($date, [['Kas', 1000, 0], ['Pendapatan Penjualan', 0, 1000]]);

        foreach (array_keys(CoretaxCatalog::LAMPIRAN) as $lampiran) {
            foreach (['client.reports.coretax', 'client.reports.coretax.mapping', 'client.reports.coretax.fiscal'] as $route) {
                $this->get(route($route, ['lampiran' => $lampiran, 'tahun' => $this->year]))->assertOk();
            }
        }
        $this->get(route('client.reports.coretax'))->assertOk()->assertSee('Lampiran 1B - Manufaktur')->assertSee('Harga Pokok Produksi')->assertSee('Pemetaan Akun');
        $this->get(route('client.reports.coretax', ['export' => 'excel']))->assertOk();
        $this->get(route('client.reports.coretax', ['export' => 'csv']))->assertOk();
        $this->get(route('client.reports.coretax', ['export' => 'pdf']))->assertOk();
        $this->get(route('client.reports.index'))->assertOk()->assertSee('Rekonsiliasi Coretax');

        $staff = User::factory()->create(['company_id' => $this->cid, 'role' => 'client_staff', 'is_active' => true]);
        $this->actingAs($staff);
        $this->get(route('client.reports.coretax.mapping'))->assertOk()->assertDontSee('Simpan Pemetaan');
        $rent = $this->acc('Beban Sewa');
        $this->post(route('client.reports.coretax.mapping.save'), ['codes' => [$rent->id => '5322']])->assertRedirect(route('client.dashboard'));
        $this->assertNull($rent->fresh()->coretax_code);
    }

    private function positionDoc(array $params = []): array
    {
        $builder = new \App\Services\Reports\ReportBuilder($this->cid);

        return $builder->coretax(new \Illuminate\Http\Request(['lampiran' => '1C', 'tahun' => $this->year] + $params));
    }

    public function test_financial_position_sits_side_by_side_like_the_form(): void
    {
        $this->journal($this->year . '-03-01', [['Kas', 500000, 0], ['Modal Disetor', 0, 500000]]);
        $this->journal($this->year . '-03-02', [['Kendaraan', 200000, 0], ['Hutang Usaha', 0, 200000]]);
        $this->journal($this->year . '-03-03', [['Beban Penyusutan', 20000, 0], ['Akumulasi Penyusutan Kendaraan', 0, 20000]]);

        foreach ([['tampilan' => 'ringkas'], ['tampilan' => 'rinci']] as $mode) {
            $doc = $this->positionDoc($mode);
            $pair = $doc['side_by_side'];

            $codes = fn (array $rows) => array_column(array_filter(array_map(fn ($r) => $r['cells'][0] ?? null, $rows)), null);
            $this->assertSame('Aset Lancar', $pair['left'][0]['label']);
            $this->assertSame('Liabilitas Jangka Pendek', $pair['right'][0]['label']);
            $this->assertTrue(collect($codes($pair['left']))->every(fn ($c) => $c[0] === '1'));
            $this->assertTrue(collect($codes($pair['right']))->every(fn ($c) => in_array($c[0], ['2', '3'], true)));

            // Jumlah akhir kedua sisi sejajar dan nilainya sama
            $this->assertSame(count($pair['left']), count($pair['right']));
            $left = end($pair['left']);
            $right = end($pair['right']);
            $this->assertSame('1700', $left['cells'][0]);
            $this->assertSame('3300', $right['cells'][0]);
            $this->assertEquals(680000, $left['cells'][2]);
            $this->assertEquals($left['cells'][2], $right['cells'][2]);

            // Baris posisi keuangan tidak lagi ikut tabel laba rugi
            $this->assertNotContains('1101', array_map(fn ($r) => $r['cells'][0] ?? null, $doc['rows']));
        }

        $row = collect($this->positionDoc(['tampilan' => 'ringkas'])['side_by_side']['left'])->first(fn ($r) => ($r['cells'][0] ?? '') === '1530');
        $this->assertEquals(-20000, $row['cells'][2]);
        $this->assertSame(1, $row['indent']);
    }

    public function test_side_by_side_appears_on_screen_print_excel_and_csv(): void
    {
        $this->journal($this->year . '-03-01', [['Kas', 500000, 0], ['Modal Disetor', 0, 500000]]);
        $params = ['lampiran' => '1C', 'tahun' => $this->year, 'tampilan' => 'ringkas'];

        // Sel kiri dan kanan bergantian dalam satu baris tabel
        foreach ([$params, $params + ['export' => 'pdf']] as $query) {
            $this->get(route('client.reports.coretax', $query))->assertOk()
                ->assertSeeInOrder(['LAPORAN LABA RUGI', 'LAPORAN POSISI KEUANGAN', 'Aset Lancar', 'Liabilitas Jangka Pendek', '1101', '2102', '1200', '2103']);
        }

        $company = Company::find($this->cid);
        $sheet = \App\Services\Reports\ReportXlsx::build($this->positionDoc(['tampilan' => 'ringkas']), $company, 'Owner');
        $this->assertSame(2, $sheet->getSheetCount());
        $this->assertSame(0, $sheet->getActiveSheetIndex());
        $position = $sheet->getSheetByName('Posisi Keuangan');
        $this->assertSame('Aset Lancar', $position->getCell('A7')->getValue());
        $this->assertSame('Liabilitas Jangka Pendek', $position->getCell('E7')->getValue());
        $this->assertSame('1101', $position->getCell('A8')->getValue());
        $this->assertEquals(500000, $position->getCell('C8')->getValue());
        $this->assertSame('2102', $position->getCell('E8')->getValue());
        $values = collect($position->toArray(null, false, false))->first(fn ($r) => ($r[0] ?? '') === '1700');
        $this->assertSame('3300', $values[4]);
        $this->assertEquals($values[2], $values[6]);

        // CSV tetap memanjang supaya mudah diolah
        $csv = $this->get(route('client.reports.coretax', $params + ['export' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString('LAPORAN POSISI KEUANGAN', $csv);
        $this->assertStringContainsString('Kas dan Setara Kas', $csv);
        $this->assertStringContainsString('Jumlah Liabilitas dan Ekuitas', $csv);
    }

    public function test_side_by_side_follows_the_language(): void
    {
        $doc = \App\Support\UiTranslator::instance()->report($this->positionDoc());
        $pair = $doc['side_by_side'];
        $this->assertSame('STATEMENT OF FINANCIAL POSITION', $pair['title']);
        $this->assertSame('Current Assets', $pair['left'][0]['label']);
        $this->assertSame('Account Name', $pair['columns'][1]['label']);
        $this->assertSame('Cash and Cash Equivalents', $pair['left'][1]['cells'][1]);
    }

    public function test_default_form_follows_the_business_type(): void
    {
        $this->assertSame('1D', CoretaxCatalog::forBusinessType('jasa'));
        $this->assertSame('1C', CoretaxCatalog::forBusinessType('dagang'));
        $this->assertSame('1C', CoretaxCatalog::forBusinessType('dagang_jasa'));
        $this->assertSame('1B', CoretaxCatalog::forBusinessType('manufaktur'));
    }

    public function test_tax_year_follows_the_fiscal_year_start(): void
    {
        Company::whereKey($this->cid)->update(['fiscal_year_start' => 7]);
        $this->assertSame(['2026-07-01', '2027-06-30'], CoretaxReconciliation::range($this->cid, 2026));
    }
}
