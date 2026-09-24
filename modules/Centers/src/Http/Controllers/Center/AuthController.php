<?php

namespace Modules\Centers\Http\Controllers\Center;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Routing\Controller;

#[Group(name: 'Center / Auth', description: 'Center authentication via phone number + OTP.')]
class AuthController extends Controller
{

}
