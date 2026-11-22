<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Users\Pages\EditUser;

class EditCustomer extends EditUser
{
    protected static string $resource = CustomerResource::class;
}
