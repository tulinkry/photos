<?php

namespace Tulinkry\DI;

use Nette\Application\IRouter;
use Nette\Application\Routers\Route;
use Nette\Application\Routers\RouteList;
use Nette\DI\CompilerExtension;
use Nette\Utils\AssertionException;
use Nette\Utils\Validators;

class PhotosExtension extends CompilerExtension
{

    private $defaults = array (
        'directory' => "%appDir%/img/photos"
    );

    public function loadConfiguration () {
        $config = $this->getConfig($this->defaults);
        $builder = $this->getContainerBuilder();

        Validators::assertField( $config, 'directory', 'string:1..', 'configuration of \'%\' in the photos extension' );

        $this->compiler->parseServices($builder, $this->loadFromFile(__DIR__ . '/config.neon'), $this->name);

        $builder -> addDefinition( $this -> prefix( "parameters" ) )
                 -> setClass( "Tulinkry\Photos\Services\ParameterService", [$config] );
    }

    public function beforeCompile () {
        $builder = $this -> getContainerBuilder();

        $urls = [
            // url => action
            "users/<userId>" => array("Download", "albums"),
            "[users/<userId>/]albums/<albumId>" => array("Download", "photos"),
            "[users/<userId>/albums/<albumId>/]photos/<photoId>" => array("Download", "photo"),
            "[users/<userId>/albums/<albumId>/]content/<photoId>" => array("Download", "content"),

            "users/new" => array("User", "new"),
            "users/<userId>/update" => array("User", "update"),
            "users/<userId>/albums/new" => array("Album", "new"),
            "[users/<userId>/]albums/<albumId>/update" => array("Album", "update"),
            "[users/<userId>/]albums/<albumId>/photos/new" => array("Photo", "new"),
            "[users/<userId>/albums/<albumId>/]photos/<photoId>/update" => array("Photo", "update"),
            //"[<userId>/albums/]<albumId>/photos" => array("Upload", "photos"),
            //"[<userId>/albums/<albumId>/]photos/<photoId>" => array("Upload", "photo"),
            //"[<userId>/albums/<albumId>/]content/<photoId>" => array("Upload", "content")
        ];

        $router = $builder -> getByType( 'Nette\Application\IRouter' ) ?: 'router';
        if ( $builder -> hasDefinition( $router ) ) {
            foreach($urls as $url => $route) {
                list($presenter, $action) = $route;
                $builder -> getDefinition( $router )
                        -> addSetup( '\Tulinkry\DI\PhotosExtension::modifyRouter(?, ?, ?, ?)',
                            [ $url, $presenter, $action, '@self' ] );
            }
        }

        $presenterFactory = $builder -> getByType( 'Nette\Application\IPresenterFactory' ) ?: 'nette.presenterFactory';
        if ( $builder -> hasDefinition( $presenterFactory ) ) {
            $builder -> getDefinition( $presenterFactory )
                    -> addSetup( 'setMapping', array (
                        // nette 2.4 autoloads presenters and autowires them
                        array ( 'Photos' => 'Tulinkry\Photos\Presenters\*Controller' )
                    ) );
        }
    }

    public static function modifyRouter ( $url, $presenter, $action, IRouter &$router ) {
        if ( ! $router instanceof RouteList ) {
            throw new AssertionException( 'Your router should be an instance of Nette\Application\Routers\RouteList' );
        }

        $router[] = $newRouter = new Route( $url,
            array ( 'module' => 'Photos',
                    'presenter' => $presenter,
                    'action' => $action ) );

        $lastKey = count( $router ) - 1;
        foreach ( $router as $i => $route ) {
            if ( $i === $lastKey ) {
                break;
            }
            $router[ $i + 1 ] = $route;
        }

        $router[ 0 ] = $newRouter;
    }

}
