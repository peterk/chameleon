<?php
/**
 * File containing the BeforeInitialize class
 *
 * This file is part of the MediaWiki skin Chameleon.
 *
 * @copyright 2013 - 2016, Stephan Gambke, mwjames
 * @license   GNU General Public License, version 3 (or any later version)
 *
 * The Chameleon skin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option) any
 * later version.
 *
 * The Chameleon skin is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @file
 * @ingroup Skins
 */

namespace Skins\Chameleon\Hooks;

use Bootstrap\BootstrapManager;
use RuntimeException;

/**
 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SetupAfterCache
 *
 * @since 1.0
 *
 * @author mwjames
 * @author Stephan Gambke
 * @since 1.0
 * @ingroup Skins
 */
class SetupAfterCache {

	protected $bootstrapManager = null;
	protected $configuration = [];
	protected $request;

	/**
	 * @since  1.0
	 *
	 * @param BootstrapManager $bootstrapManager
	 * @param array $configuration
	 * @param \WebRequest $request
	 */
	public function __construct( BootstrapManager $bootstrapManager, array &$configuration, \WebRequest $request ) {
		$this->bootstrapManager = $bootstrapManager;
		$this->configuration = &$configuration;
		$this->request = $request;
	}

	/**
	 * @since  1.0
	 *
	 * @return self
	 */
	public function process() {

		$this->setInstallPaths();
		$this->addLateSettings();
		$this->registerCommonBootstrapModules();
		$this->registerExternalScssModules();
		$this->registerExternalStyleVariables();

		return $this;
	}

	/**
	 * @since 1.0
	 *
	 * @param array $configuration
	 */
	public function adjustConfiguration( array &$configuration ) {

		foreach ( $this->configuration as $key => $value ) {
			$configuration[ $key ] = $value;
		}
	}

	/**
	 * Set local and remote base path of the Chameleon skin
	 */
	protected function setInstallPaths() {

		$this->configuration[ 'chameleonLocalPath' ] = $this->configuration['wgStyleDirectory'] . '/chameleon';
		$this->configuration[ 'chameleonRemotePath' ] = $this->configuration['wgStylePath'] . '/chameleon';
	}

	protected function addLateSettings() {

		$this->addChameleonToVisualEditorSupportedSkins();
		$this->addResourceModules();
		$this->setLayoutFile();
	}

	protected function registerCommonBootstrapModules() {

		$this->bootstrapManager->addAllBootstrapModules();

		$this->bootstrapManager->addExternalModule(
			$this->configuration[ 'chameleonLocalPath' ] . '/resources/styles/core.scss',
			$this->configuration[ 'chameleonRemotePath' ] . '/resources/styles/'
		);

		$GLOBALS[ 'wgResourceModules' ][ 'skin.chameleon.fontawesome' ] = [
			'class'         => 'SCSS\\ResourceLoaderSCSSModule',
			'localBasePath' => $GLOBALS[ 'wgStyleDirectory' ] . "/chameleon/resources/fontawesome/scss/",
			'position'      => 'top',
			'styles'        => [ "fontawesome", "fa-solid" ],
			'variables'     => [ "fa-font-path" => $GLOBALS[ 'wgStylePath' ] . "/chameleon/resources/fontawesome/webfonts" ],
			'dependencies'  => [],
			'cachetriggers' => [
				'LocalSettings.php' => null,
				'composer.lock'     => null,
			],
		];

	}

	protected function registerExternalScssModules() {

		if ( $this->hasConfigurationOfTypeArray( 'egChameleonExternalStyleModules' ) ) {

			foreach ( $this->configuration[ 'egChameleonExternalStyleModules' ] as $localFile => $remotePath ) {

				list( $localFile, $remotePath ) = $this->matchAssociativeElement( $localFile, $remotePath );

				$this->bootstrapManager->addExternalModule(
					$this->isReadableFile( $localFile ),
					$remotePath
				);
			}
		}
	}

	protected function registerExternalStyleVariables() {

		if ( $this->hasConfigurationOfTypeArray( 'egChameleonExternalStyleVariables' ) ) {

			foreach ( $this->configuration[ 'egChameleonExternalStyleVariables' ] as $key => $value ) {
				$this->bootstrapManager->setScssVariable( $key, $value );
			}
		}
	}

	/**
	 * @param $id
	 * @return bool
	 */
	private function hasConfiguration( $id ) {
		return isset( $this->configuration[ $id ] );
	}

	/**
	 * @param string $id
	 * @return bool
	 */
	private function hasConfigurationOfTypeArray( $id ) {
		return $this->hasConfiguration( $id ) && is_array( $this->configuration[ $id ] );
	}

	/**
	 * @param $localFile
	 * @param $remotePath
	 * @return array
	 */
	private function matchAssociativeElement( $localFile, $remotePath ) {

		if ( is_integer( $localFile ) ) {
			return [ $remotePath, '' ];
		}

		return [ $localFile, $remotePath ];
	}

	/**
	 * @param string $file
	 * @return string
	 */
	private function isReadableFile( $file ) {

		if ( is_readable( $file ) ) {
			return $file;
		}

		throw new RuntimeException( "Expected an accessible {$file} file" );
	}

	protected function addChameleonToVisualEditorSupportedSkins() {

		// if Visual Editor is installed and there is a setting to enable or disable it
		if ( $this->hasConfiguration( 'wgVisualEditorSupportedSkins' ) && $this->hasConfiguration( 'egChameleonEnableVisualEditor' ) ) {

			// if VE should be enabled
			if ( $this->configuration[ 'egChameleonEnableVisualEditor' ] === true ) {

				// if Chameleon is not yet in the list of VE-enabled skins
				if ( !in_array( 'chameleon', $this->configuration[ 'wgVisualEditorSupportedSkins' ] ) ) {
					$this->configuration[ 'wgVisualEditorSupportedSkins' ][] = 'chameleon';
				}

			} else {
				// remove all entries of Chameleon from the list of VE-enabled skins
				$this->configuration[ 'wgVisualEditorSupportedSkins' ] = array_diff(
					$this->configuration[ 'wgVisualEditorSupportedSkins' ],
					[ 'chameleon' ]
				);
			}
		}
	}

	protected function addResourceModules() {
		$this->configuration[ 'wgResourceModules' ][ 'skin.chameleon.jquery-sticky' ] = [
			'localBasePath'  => $this->configuration[ 'chameleonLocalPath' ] . '/resources/js',
			'remoteBasePath' => $this->configuration[ 'chameleonRemotePath' ] . '/resources/js',
			'group'          => 'skin.chameleon',
			'skinScripts'    => [ 'chameleon' => [ 'sticky-kit/jquery.sticky-kit.js', 'Components/Modifications/sticky.js' ] ]
		];
	}

	protected function setLayoutFile() {

		$layout = $this->request->getVal( 'uselayout' );

		if ( $layout !== null &&
			$this->hasConfigurationOfTypeArray( 'egChameleonAvailableLayoutFiles' ) &&
			array_key_exists( $layout, $this->configuration[ 'egChameleonAvailableLayoutFiles' ] ) ) {

			$this->configuration[ 'egChameleonLayoutFile' ] = $this->configuration[ 'egChameleonAvailableLayoutFiles' ][ $layout ];
		}
	}

}
