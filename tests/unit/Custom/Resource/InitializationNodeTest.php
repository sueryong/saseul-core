<?php

namespace Saseul\Test\Unit\Custom\Resource;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Saseul\Common\AbstractResource;
use Saseul\Constant\Role;
use Saseul\Core\Env;
use Saseul\Custom\Resource\InitializationNode;
use Saseul\System\Database;
use Saseul\System\Key;
use Saseul\Util\DateTime;

class InitializationNodeTest extends TestCase
{
    private $sut;
    private $sutName;

    protected function setUp(): void
    {
        $this->sut = new InitializationNode();
        $this->sutName = (new ReflectionClass(get_class($this->sut)))->getShortName();

        Env::$nodeInfo['address'] = '0x6f1b0f1ae759165a92d2e7d0b4cae328a1403aa5e35a85';
        Env::$genesis['address'] = '0x6f1b0f1ae759165a92d2e7d0b4cae328a1403aa5e35a85';
    }

    protected function tearDown(): void
    {
        Env::$nodeInfo['address'] = '0x6f1b0f1ae759165a92d2e7d0b4cae328a1403aa5e35a85';
        $db = Database::getInstance();
        $db->getTrackerCollection()->drop();
    }

    public function testSutInheritsAbstractRequest(): void
    {
        // Assert
        $this->assertInstanceOf(AbstractResource::class, $this->sut);
    }

    public function testGivenInvalidFromAddressThenGetValidityMethodReturnsFalse(): void
    {
        // Arrange
        $request = [
            'type' => $this->sutName,
            'from' => '0x6f258c97ad7848aef661465018dc48e55131eff91c4e20',
            'timestamp' => DateTime::Microtime()
        ];

        $thash = hash('sha256', json_encode($request, JSON_THROW_ON_ERROR, 512));
        $privateKey = 'a745fbb3860f243293a66a5fcadf70efc1fa5fa5f0254b3100057e753ef0d9bb';
        $publicKey = '52017bcb4caca8911b3830c281d10f79359ceb3fbe061c990e043ccb589fccc3';
        $signature = Key::makeSignature($thash, $privateKey, $publicKey);
        $this->sut->initialize($request, $thash, $publicKey, $signature);

        // Act
        $actual = $this->sut->getValidity();

        // Assert
        $this->assertFalse($actual);
    }

    public function testGivenGenesisNodeAddressThenRoleIsValidator(): void
    {
        // Act
        $this->sut->process();

        // Assert
        $actual = $this->sut->getResponse();
        $this->assertSame(Role::VALIDATOR, $actual['role']);
    }

    public function testGivenLightNodeAddressThenRoleIsLight(): void
    {
        // Arrange
        Env::$nodeInfo['address'] = '0x6f258c97ad7848aef661465018dc48e55131eff91c4e20';

        // Act
        $this->sut->process();

        // Assert
        $actual = $this->sut->getResponse();
        $this->assertSame(Role::LIGHT, $actual['role']);
    }
}
