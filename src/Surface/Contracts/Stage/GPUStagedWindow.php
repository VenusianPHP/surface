<?php

namespace Surface\Contracts\Stage;

use Surface\Contracts\Drawing\GPUDrawTarget;

/** A stage a GPU engine draws through an Executor. */
interface GPUStagedWindow extends StagedWindow, GPUDrawTarget {}
