<?php

namespace App;

use Nette;
use Nette\Application\Routers\Route;
use Nette\Application\Routers\RouteList;


final class RouterFactory
{
	use Nette\StaticClass;

	/**
	 * @return Nette\Application\IRouter
	 */
	public static function createRouter()
	{
		$router = new RouteList;

        $admin = new RouteList('Admin');
        $admin[] = new Route('admin/<presenter>/<action>[/<id>]', 'Homepage:default');
        $router[] = $admin;

        $guest = new RouteList('Public');
        $guest[] = new Route('<presenter>/<action>[/<id>][/<page>]', 'Homepage:default');
        $router[] = $guest;

		return $router;
	}
}