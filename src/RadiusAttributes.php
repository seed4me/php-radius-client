<?php

namespace Seed4Me\RadiusClient;

class RadiusAttributes
{
    const USER_NAME = 1;
    const USER_PASSWORD = 2;
    const CHAP_PASSWORD = 3;
    const NAS_IP_ADDRESS = 4;
    const NAS_PORT = 5;
    const SERVICE_TYPE = 6;
    const FRAMED_PROTOCOL = 7;
    const FRAMED_IP_ADDRESS = 8;
    const FRAMED_IP_NETMASK = 9;
    const FRAMED_ROUTING = 10;
    const FILTER_ID = 11;
    const FRAMED_MTU = 12;
    const FRAMED_COMPRESSION = 13;
    const LOGIN_IP_HOST = 14;
    const LOGIN_SERVICE = 15;
    const LOGIN_TCP_PORT = 16;
    const REPLY_MESSAGE = 18;
    const CALLBACK_NUMBER = 19;
    const CALLBACK_ID = 20;
    const FRAMED_ROUTE = 22;
    const FRAMED_IPX_NETWORK = 23;
    const STATE = 24;
    const RADIUS_CLASS = 25; //renamed from CLASS to RADIUS_CLASS because of Class keyword reserved in PHP
    const VENDOR_SPECIFIC = 26;
    const SESSION_TIMEOUT = 27;
    const IDLE_TIMEOUT = 28;
    const TERMINATION_ACTION = 29;
    const CALLED_STATION_ID = 30;
    const CALLING_STATION_ID = 31;
    const NAS_IDENTIFIER = 32;
    const PROXY_STATE = 33;
    const LOGIN_LAT_SERVICE = 34;
    const LOGIN_LAT_NODE = 35;
    const LOGIN_LAT_GROUP = 36;
    const FRAMED_APPLETALK_LINK = 37;
    const FRAMED_APPLETALK_NETWORK = 38;
    const FRAMED_APPLETALK_ZONE = 39;
    const CHAP_CHALLENGE = 60;
    const NAS_PORT_TYPE = 61;
    const PORT_LIMIT = 62;
    const LOGIN_LAT_PORT = 63;
    const PROMPT = 76;
    const EAP_MESSAGE = 79;
    const MESSAGE_AUTHENTICATOR = 80;
}
