<?php

use App\Models\CustomerSubscription;

it('truncates MySQL user names to the MySQL / Forge 32 character limit', function () {
    $long = str_repeat('a', 40);
    $limited = CustomerSubscription::limitMysqlUserName($long);
    expect($limited)->toHaveLength(CustomerSubscription::MYSQL_USER_NAME_MAX_LENGTH);
    expect($limited)->toBe(str_repeat('a', 32));
});

it('leaves short user names unchanged', function () {
    expect(CustomerSubscription::limitMysqlUserName('short_user'))->toBe('short_user');
});
