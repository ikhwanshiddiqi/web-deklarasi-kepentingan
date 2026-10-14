<?php

namespace Tests\Feature;

use App\Http\Requests\StoreDeclarationRequest;
use App\Models\ConflictDeclaration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Validasi StoreDeclarationRequest — konsistensi Bagian B ↔ C (Task 6.2).
 *
 * Diuji secara terisolasi: endpoint POST /declarations masih stub (501) sampai
 * Task 6.3, sehingga Form Request diinstansiasi langsung lalu validator-nya
 * dijalankan manual tanpa menyentuh route.
 *
 * Requirements: 4.4, 4.5, 4.9, 4.10
 */
class StoreDeclarationRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bangun Form Request dari payload dan kembalikan validator-nya.
     *
     * @param  array<string, mixed>  $payload
     */
    private function validatorFor(array $payload): \Illuminate\Validation\Validator
    {
        // Subkelas anonim mengekspos getValidatorInstance() yang `protected`
        // pada FormRequest sehingga validator dapat dijalankan terisolasi
        // tanpa route (POST /declarations masih stub sampai Task 6.3).
        $request = new class extends StoreDeclarationRequest {
            public function buildValidator(): \Illuminate\Validation\Validator
            {
                /** @var \Illuminate\Validation\Validator $validator */
                $validator = $this->getValidatorInstance();

                return $validator;
            }
        };

        $request->initialize([], $payload, [], [], [], ['REQUEST_METHOD' => 'POST']);
        $request->setContainer(app())->setRedirector(app('redirect'));

        return $request->buildValidator();
    }

    public function test_none_tanpa_kategori_dan_rincian_lolos(): void
    {
        $validator = $this->validatorFor([
            'declaration_status' => ConflictDeclaration::STATUS_NONE,
        ]);

        $this->assertTrue($validator->passes());
    }

    public function test_none_dengan_kategori_dipilih_ditolak(): void
    {
        $validator = $this->validatorFor([
            'declaration_status' => ConflictDeclaration::STATUS_NONE,
            'categories' => ['family_relationship'],
        ]);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('categories', $validator->errors()->toArray());
    }

    public function test_none_dengan_rincian_terisi_ditolak(): void
    {
        $validator = $this->validatorFor([
            'declaration_status' => ConflictDeclaration::STATUS_NONE,
            'details' => [
                [
                    'category_key' => 'family_relationship',
                    'related_party_name' => 'Andi',
                    'situation_explanation' => 'Hubungan saudara kandung.',
                ],
            ],
        ]);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('details', $validator->errors()->toArray());
    }

    public function test_has_conflict_tanpa_kategori_ditolak(): void
    {
        $validator = $this->validatorFor([
            'declaration_status' => ConflictDeclaration::STATUS_HAS_CONFLICT,
            'details' => [
                [
                    'category_key' => 'family_relationship',
                    'related_party_name' => 'Andi',
                    'situation_explanation' => 'Hubungan saudara kandung.',
                ],
            ],
        ]);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('categories', $validator->errors()->toArray());
    }

    public function test_has_conflict_dengan_kategori_tanpa_rincian_ditolak(): void
    {
        $validator = $this->validatorFor([
            'declaration_status' => ConflictDeclaration::STATUS_HAS_CONFLICT,
            'categories' => ['family_relationship'],
        ]);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('details', $validator->errors()->toArray());
    }

    public function test_has_conflict_rincian_kategori_tidak_terpilih_ditolak(): void
    {
        $validator = $this->validatorFor([
            'declaration_status' => ConflictDeclaration::STATUS_HAS_CONFLICT,
            'categories' => ['family_relationship'],
            'details' => [
                [
                    // Kategori valid tapi TIDAK dicentang di Bagian B (Req 4.9).
                    'category_key' => 'procurement_finance',
                    'related_party_name' => 'PT Vendor',
                    'situation_explanation' => 'Terlibat seleksi vendor.',
                ],
            ],
        ]);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('details.0.category_key', $validator->errors()->toArray());
    }

    public function test_has_conflict_dengan_rincian_valid_lolos(): void
    {
        $validator = $this->validatorFor([
            'declaration_status' => ConflictDeclaration::STATUS_HAS_CONFLICT,
            'categories' => ['family_relationship'],
            'details' => [
                [
                    'category_key' => 'family_relationship',
                    'related_party_name' => 'Andi',
                    'situation_explanation' => 'Hubungan saudara kandung di unit yang sama.',
                ],
            ],
        ]);

        $this->assertTrue($validator->passes());
    }

    public function test_has_conflict_rincian_tanpa_nama_pihak_ditolak(): void
    {
        $validator = $this->validatorFor([
            'declaration_status' => ConflictDeclaration::STATUS_HAS_CONFLICT,
            'categories' => ['family_relationship'],
            'details' => [
                [
                    'category_key' => 'family_relationship',
                    'related_party_name' => '',
                    'situation_explanation' => 'Hubungan saudara kandung.',
                ],
            ],
        ]);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('details.0.related_party_name', $validator->errors()->toArray());
    }

    public function test_has_conflict_rincian_tanpa_penjelasan_ditolak(): void
    {
        $validator = $this->validatorFor([
            'declaration_status' => ConflictDeclaration::STATUS_HAS_CONFLICT,
            'categories' => ['family_relationship'],
            'details' => [
                [
                    'category_key' => 'family_relationship',
                    'related_party_name' => 'Andi',
                    'situation_explanation' => '',
                ],
            ],
        ]);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('details.0.situation_explanation', $validator->errors()->toArray());
    }

    public function test_has_conflict_rincian_dengan_daftar_html_lolos(): void
    {
        $validator = $this->validatorFor([
            'declaration_status' => ConflictDeclaration::STATUS_HAS_CONFLICT,
            'categories' => ['family_relationship'],
            'details' => [
                [
                    'category_key' => 'family_relationship',
                    'related_party_name' => 'Andi',
                    'situation_explanation' => '<ol><li>Saudara kandung dari pihak lain</li><li>Berada di unit kerja yang sama</li></ol>',
                ],
            ],
        ]);

        $this->assertTrue($validator->passes());
    }

    public function test_has_conflict_rincian_dengan_html_kosong_ditolak(): void
    {
        $validator = $this->validatorFor([
            'declaration_status' => ConflictDeclaration::STATUS_HAS_CONFLICT,
            'categories' => ['family_relationship'],
            'details' => [
                [
                    'category_key' => 'family_relationship',
                    'related_party_name' => 'Andi',
                    'situation_explanation' => '<p><br></p>',
                ],
            ],
        ]);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('details.0.situation_explanation', $validator->errors()->toArray());
    }

    public function test_status_tidak_valid_ditolak(): void
    {
        $validator = $this->validatorFor([
            'declaration_status' => 'maybe',
        ]);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('declaration_status', $validator->errors()->toArray());
    }
}
