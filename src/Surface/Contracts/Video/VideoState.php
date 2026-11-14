<?php

namespace Surface\Contracts\Video;

/** Where a VideoPlayer stands. */
enum VideoState: string
{
    /** Holding its position: before the first play(), after pause(), after a seek while paused. */
    case Paused = 'paused';

    case Playing = 'playing';

    /** The last picture has had its time; play() starts again from the start. */
    case Ended = 'ended';

    /** The source did not open, or a worker thread stopped: failure() says why. Nothing plays. */
    case Failed = 'failed';

    case Closed = 'closed';
}
