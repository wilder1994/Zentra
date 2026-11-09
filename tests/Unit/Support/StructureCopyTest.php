<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\Client;
use App\Models\StructureType;
use App\Support\Structure\StructureCopy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class StructureCopyTest extends TestCase
{
    #[Test]
    public function falls_back_to_unidad_without_type(): void
    {
        $client = new Client;

        $this->assertSame('Unidad', StructureCopy::unitSingular($client));
        $this->assertSame('Unidades', StructureCopy::unitPlural($client));
        $this->assertSame('Agregar unidad', StructureCopy::addButton($client));
    }

    #[Test]
    public function uses_structure_type_name(): void
    {
        $type = new StructureType(['name' => 'Torre']);
        $client = new Client;
        $client->setRelation('structureType', $type);

        $this->assertSame('Torre', StructureCopy::unitSingular($client));
        $this->assertSame('Torres', StructureCopy::unitPlural($client));
        $this->assertSame('Agregar torre', StructureCopy::addButton($client));
        $this->assertStringContainsString('torres', StructureCopy::emptyTree($client, 'Sede Norte'));
    }
}
