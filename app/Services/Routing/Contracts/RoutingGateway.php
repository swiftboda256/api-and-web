<?php

namespace App\Services\Routing\Contracts;

use App\Services\Routing\RouteResult;
use Clickbar\Magellan\Data\Geometries\Point;

interface RoutingGateway
{
    /**
     * Compute a driving route through an ordered list of waypoints: the first point is the
     * origin, the last is the destination, and any points between are visited in order.
     * Requires at least 2 waypoints.
     *
     * @param  list<Point>  $waypoints
     */
    public function computeRoute(array $waypoints): RouteResult;
}
