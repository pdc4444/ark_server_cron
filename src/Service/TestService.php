<?php
// src/service/TestService.php
namespace App\Service;

class TestService
{
    public function firstService()
    {
        $test_output = [
            'here is the first test line',
            'second test line',
            'third test line'
        ];

        shuffle($test_output);
        return current($test_output);
    }
}