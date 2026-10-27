<?php

gf_paystack_test_check(rgars(array('a' => array('b' => 'c')), 'a/b') === 'c', 'rgars resolves nested keys');
