<?php

namespace Tulinkry\Photos\Utils;

use Nette;
use Nette\Utils\Json;
use Nette\Utils\Arrays;
use Nette\Neon\Neon;

trait TMetadata
{

	private function getLoadMetadataFormats() {
		return 	array(
			'json' => function($string) {
				return (object) Json::decode($string);
			},
			'neon' => function($string) {
				return (object) Neon::decode($string);
			});
	}

	private function getSaveMetadataFormats() {
		return 	array(
			'json' => function($object) {
				return Json::encode($object, Json::PRETTY);
			},
			'neon' => function($object) {
				return Neon::encode($object);
			});
	}

	protected function loadMetadata($directory, $default = NULL) {
		foreach($this->getLoadMetadataFormats() as $format => $decoder) {
			if(($content = @file_get_contents($directory . DIRECTORY_SEPARATOR . '.metadata.' . $format)) !== FALSE &&
			   ($content = $decoder ($content))) {
				return $content;
			}
		}
		return $default;
	}

	protected function saveMetadata($directory, $data, $format = 'json') {
		$formats = $this->getSaveMetadataFormats();
		if(!array_key_exists($format, $formats) && !is_callable($formats[$format]($data))) {
			throw new Nette\InvalidArgumentException("Format '$format' is not in the allowed values.");
		}
		return @file_put_contents($directory . DIRECTORY_SEPARATOR . '.metadata.' . $format, $formats[$format]($data)) !== FALSE;
	}

	protected function addMetadata($directory, $data, $format = 'json') {
		$loaded = $this->loadMetadata($directory, array());
		return $this->saveMetadata($directory, (object) Arrays::mergeTree( (array) $data, (array) $loaded), $format);
	}
}