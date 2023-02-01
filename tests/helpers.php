<?php

function isUnitTest() {
	return !empty($GLOBALS['argv']) && $GLOBALS['argv'][1] === '--group=unit';
}
