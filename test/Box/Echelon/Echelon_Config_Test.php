<?php
namespace Box\Echelon;
use Bart\BaseTestCase;
use Bart\Configuration\ConfigurationTest;

class Echelon_Config_Test extends BaseTestCase
{
	public function testReadme()
	{
		ConfigurationTest::assertREADME($this, 'Box\Echelon\Echelon_Config', 'echelon.conf');
	}
}

