<?php

use PHPUnit\Framework\TestCase;
use Saseul\Common\AbstractRequest;
use Saseul\Custom\Request\GetRole;

class GetRoleTest extends TestCase
{
    public function testSutInheritsAbstractRequest()
    {
        # Arrange
        $sut = new GetRole();

        # Assert
        $this->assertInstanceOf(AbstractRequest::class, $sut);
    }
}