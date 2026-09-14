<?php

namespace Surface\Contracts\Drawing;

/** Primitive topologies every engine executes. No fans — Metal and MoltenVK lack them. */
enum Topology: int
{
    case POINTS = 0;
    case LINES = 1;
    case LINE_STRIP = 2;
    case TRIANGLES = 3;
    case TRIANGLE_STRIP = 4;
}
