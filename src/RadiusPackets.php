<?php

namespace Seed4Me\RadiusClient;

class RadiusPackets
{
    const ACCESS_REQUEST = 1;
    const ACCESS_ACCEPT = 2;
    const ACCESS_REJECT = 3;
    const ACCOUNTING_REQUEST = 4;
    const ACCOUNTING_RESPONSE = 5;
    const ACCESS_CHALLENGE = 11;
    const STATUS_SERVER = 12;
    const STATUS_CLIENT = 13;
    const RESERVED = 255;
}
