<?php

/*********************************************************************
 *
 * Pure PHP radius class
 *
 * This Radius class is a radius client implementation in pure PHP
 * following the RFC 2865 rules (http://www.ietf.org/rfc/rfc2865.txt)
 *
 * This class works with at least the following RADIUS servers:
 *  - Authenex Strong Authentication System (ASAS) with two-factor authentication
 *  - FreeRADIUS, a free Radius server implementation for Linux and *nix environments
 *  - Microsoft Radius server IAS
 *  - Microsoft Windows Server 2016 (Network Policy Server)
 *  - Microsoft Windows Server 2012 R2 (Network Policy Server)
 *  - Microsoft Windows Server 2019 Standard (Network Policy Server)
 *  - Mideye RADIUS server (http://www.mideye.com)
 *  - Radl, a free Radius server for Windows
 *  - RSA SecurID
 *  - VASCO Middleware 3.0 server
 *  - WinRadius, Windows Radius server (free for 5 users)
 *  - ZyXEL ZyWALL OTP (Authenex ASAS branded by ZyXEL, cheaper)
 *
 *
 * LICENCE
 *
 *   Copyright (c) 2008, SysCo systemes de communication sa
 *   SysCo (tm) is a trademark of SysCo systemes de communication sa
 *   (http://www.sysco.ch/)
 *   All rights reserved.
 *
 *   Copyright (c) 2016, Drew Phillips
 *   (https://drew-phillips.com)
 *
 *   This file is part of the Pure PHP radius class
 *
 *   Pure PHP radius class is free software; you can redistribute it and/or
 *   modify it under the terms of the GNU Lesser General Public License as
 *   published by the Free Software Foundation, either version 3 of the License,
 *   or (at your option) any later version.
 *
 *   Pure PHP radius class is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU Lesser General Public License for more details.
 *
 *   You should have received a copy of the GNU Lesser General Public
 *   License along with Pure PHP radius class.
 *   If not, see <http://www.gnu.org/licenses/>.
 *
 *
 * @author: SysCo/al
 * @author: Drew Phillips <drew@drew-phillips.com>
 * @since CreationDate: 2008-01-04
 * @copyright (c) 2008 by SysCo systemes de communication sa
 * @copyright (c) 2016 by Drew Phillips
 * @version 2.5.1
 * @link http://developer.sysco.ch/php/
 * @link developer@sysco.ch
 * @link https://github.com/dapphp/radius
 * @link drew@drew-phillips.com
 */

namespace Seed4Me\RadiusClient;

/**
 * A pure PHP RADIUS client implementation.
 *
 * Originally created by SysCo/al based on radius.class.php v1.2.2
 * Modified for PHP5 & PHP7 compatibility by Drew Phillips
 * Switched from using ext/sockets to streams.
 *
 */
class Radius
{
    /** @var int Access-Request packet type identifier */
    const TYPE_ACCESS_REQUEST      = 1;

    /** @var int Access-Accept packet type identifier */
    const TYPE_ACCESS_ACCEPT       = 2;

    /** @var int Access-Reject packet type identifier */
    const TYPE_ACCESS_REJECT       = 3;

    /** @var int Accounting-Request packet type identifier */
    const TYPE_ACCOUNTING_REQUEST  = 4;

    /** @var int Accounting-Response packet type identifier */
    const TYPE_ACCOUNTING_RESPONSE = 5;

    /** @var int Access-Challenge packet type identifier */
    const TYPE_ACCESS_CHALLENGE    = 11;

    /** @var int Reserved packet type */
    const TYPE_RESERVED            = 255;


    /** @var string RADIUS server hostname or IP address */
    protected $server;

    /** @var string Shared secret with the RADIUS server */
    protected $secret;

    /** @var string RADIUS suffix (default is '') */
    protected $suffix;

    /** @var int Timeout for receiving UDP response packets (default = 5 seconds) */
    protected $timeout;

    /** @var int Authentication port (default = 1812) */
    protected $authenticationPort;

    /** @var int Accounting port (default = 1813) */
    protected $accountingPort;

    /** @var string Network Access Server (client) IP Address */
    protected $nasIpAddress;

    /** @var string NAS port. Physical port of the NAS authenticating the user */
    protected $nasPort;

    /** @var string Encrypted password, as described in RFC 2865 */
    protected $encryptedPassword;

    /** @var int Request-Authenticator, 16 octets random number */
    protected $requestAuthenticator;

    /** @var int Request-Authenticator from the response */
    protected $responseAuthenticator;

    /** @var string Username to send to the RADIUS server */
    protected $username;

    /** @var string Password for authenticating with the RADIUS server (before encryption) */
    protected $password;

    /** @var int The CHAP identifier for CHAP-Password attributes */
    protected $chapIdentifier;

    /** @var string Identifier field for the packet to be sent */
    protected $identifierToSend;

    /** @var string Identifier field for the received packet */
    protected $identifierReceived;

    /** @var int RADIUS packet type (1=Access-Request, 2=Access-Accept, etc) */
    protected $radiusPacket;

    /** @var int Packet type received in response from RADIUS server */
    protected $radiusPacketReceived;

    /** @var array List of RADIUS attributes to send */
    protected $attributesToSend;

    /** @var array List of attributes received in response */
    protected $attributesReceived;

    /** @var bool Whether or not to enable debug output */
    protected $debug;

    /** @var array RADIUS attributes info array */
    protected $attributesInfo;

    /** @var array RADIUS packet codes info array */
    protected $radiusPackets;

    /** @var int The error code from the last operation */
    protected $errorCode;

    /** @var string The error message from the last operation */
    protected $errorMessage;


    /**
     * Radius constructor.
     *
     * @param string $radiusHost          The RADIUS server hostname or IP address
     * @param string $sharedSecret        The RADIUS server shared secret
     * @param string $radiusSuffix        The username suffix to use when authenticating
     * @param int $timeout                The timeout (in seconds) to wait for RADIUS responses
     * @param int $authenticationPort     The port for authentication requests (default = 1812)
     * @param int $accountingPort         The port for accounting requests (default = 1813)
     */
    public function __construct($radiusHost         = '127.0.0.1',
                                $sharedSecret       = '',
                                $radiusSuffix       = '',
                                $timeout            = 5,
                                $authenticationPort = 1812,
                                $accountingPort     = 1813)
    {
        $this->radiusPackets      = [];
        $this->radiusPackets[RadiusPackets::ACCESS_REQUEST]   = 'Access-Request';
        $this->radiusPackets[RadiusPackets::ACCESS_ACCEPT]   = 'Access-Accept';
        $this->radiusPackets[RadiusPackets::ACCESS_REJECT]   = 'Access-Reject';
        $this->radiusPackets[RadiusPackets::ACCOUNTING_REQUEST]   = 'Accounting-Request';
        $this->radiusPackets[RadiusPackets::ACCOUNTING_RESPONSE]   = 'Accounting-Response';
        $this->radiusPackets[RadiusPackets::ACCESS_CHALLENGE]  = 'Access-Challenge';
        $this->radiusPackets[RadiusPackets::STATUS_SERVER]  = 'Status-Server (experimental)';
        $this->radiusPackets[RadiusPackets::STATUS_CLIENT]  = 'Status-Client (experimental)';
        $this->radiusPackets[RadiusPackets::RESERVED] = 'Reserved';

        $this->attributesInfo     = [];
        $this->attributesInfo[RadiusAttributes::USER_NAME]  = array('User-Name', 'S');
        $this->attributesInfo[RadiusAttributes::USER_PASSWORD]  = array('User-Password', 'S');
        $this->attributesInfo[RadiusAttributes::CHAP_PASSWORD]  = array('CHAP-Password', 'S'); // Type (1) / Length (1) / CHAP Ident (1) / String
        $this->attributesInfo[RadiusAttributes::NAS_IP_ADDRESS]  = array('NAS-IP-Address', 'A');
        $this->attributesInfo[RadiusAttributes::NAS_PORT]  = array('NAS-Port', 'I');
        $this->attributesInfo[RadiusAttributes::SERVICE_TYPE]  = array('Service-Type', 'I');
        $this->attributesInfo[RadiusAttributes::FRAMED_PROTOCOL]  = array('Framed-Protocol', 'I');
        $this->attributesInfo[RadiusAttributes::FRAMED_IP_ADDRESS]  = array('Framed-IP-Address', 'A');
        $this->attributesInfo[RadiusAttributes::FRAMED_IP_NETMASK] = array('Framed-IP-Netmask', 'A');
        $this->attributesInfo[RadiusAttributes::FRAMED_ROUTING] = array('Framed-Routing', 'I');
        $this->attributesInfo[RadiusAttributes::FILTER_ID] = array('Filter-Id', 'T');
        $this->attributesInfo[RadiusAttributes::FRAMED_MTU] = array('Framed-MTU', 'I');
        $this->attributesInfo[RadiusAttributes::FRAMED_COMPRESSION] = array('Framed-Compression', 'I');
        $this->attributesInfo[RadiusAttributes::LOGIN_IP_HOST] = array('Login-IP-Host', 'A');
        $this->attributesInfo[RadiusAttributes::LOGIN_SERVICE] = array('Login-service', 'I');
        $this->attributesInfo[RadiusAttributes::LOGIN_TCP_PORT] = array('Login-TCP-Port', 'I');
        $this->attributesInfo[17] = array('(unassigned)', '');
        $this->attributesInfo[RadiusAttributes::REPLY_MESSAGE] = array('Reply-Message', 'T');
        $this->attributesInfo[RadiusAttributes::CALLBACK_NUMBER] = array('Callback-Number', 'S');
        $this->attributesInfo[RadiusAttributes::CALLBACK_ID] = array('Callback-Id', 'S');
        $this->attributesInfo[21] = array('(unassigned)', '');
        $this->attributesInfo[RadiusAttributes::FRAMED_ROUTE] = array('Framed-Route', 'T');
        $this->attributesInfo[RadiusAttributes::FRAMED_IPX_NETWORK] = array('Framed-IPX-Network', 'I');
        $this->attributesInfo[RadiusAttributes::STATE] = array('State', 'S');
        $this->attributesInfo[RadiusAttributes::RADIUS_CLASS] = array('Class', 'S');
        $this->attributesInfo[RadiusAttributes::VENDOR_SPECIFIC] = array('Vendor-Specific', 'S'); // Type (1) / Length (1) / Vendor-Id (4) / Vendor type (1) / Vendor length (1) / Attribute-Specific...
        $this->attributesInfo[RadiusAttributes::SESSION_TIMEOUT] = array('Session-Timeout', 'I');
        $this->attributesInfo[RadiusAttributes::IDLE_TIMEOUT] = array('Idle-Timeout', 'I');
        $this->attributesInfo[RadiusAttributes::TERMINATION_ACTION] = array('Termination-Action', 'I');
        $this->attributesInfo[RadiusAttributes::CALLED_STATION_ID] = array('Called-Station-Id', 'S');
        $this->attributesInfo[RadiusAttributes::CALLING_STATION_ID] = array('Calling-Station-Id', 'S');
        $this->attributesInfo[RadiusAttributes::NAS_IDENTIFIER] = array('NAS-Identifier', 'S');
        $this->attributesInfo[RadiusAttributes::PROXY_STATE] = array('Proxy-State', 'S');
        $this->attributesInfo[RadiusAttributes::LOGIN_LAT_SERVICE] = array('Login-LAT-Service', 'S');
        $this->attributesInfo[RadiusAttributes::LOGIN_LAT_NODE] = array('Login-LAT-Node', 'S');
        $this->attributesInfo[RadiusAttributes::LOGIN_LAT_GROUP] = array('Login-LAT-Group', 'S');
        $this->attributesInfo[RadiusAttributes::FRAMED_APPLETALK_LINK] = array('Framed-AppleTalk-Link', 'I');
        $this->attributesInfo[RadiusAttributes::FRAMED_APPLETALK_NETWORK] = array('Framed-AppleTalk-Network', 'I');
        $this->attributesInfo[RadiusAttributes::FRAMED_APPLETALK_ZONE] = array('Framed-AppleTalk-Zone', 'S');
        $this->attributesInfo[RadiusAttributes::CHAP_CHALLENGE] = array('CHAP-Challenge', 'S');
        $this->attributesInfo[RadiusAttributes::NAS_PORT_TYPE] = array('NAS-Port-Type', 'I');
        $this->attributesInfo[RadiusAttributes::PORT_LIMIT] = array('Port-Limit', 'I');
        $this->attributesInfo[RadiusAttributes::LOGIN_LAT_PORT] = array('Login-LAT-Port', 'S');
        $this->attributesInfo[RadiusAttributes::PROMPT] = array('Prompt', 'I');
        $this->attributesInfo[RadiusAttributes::EAP_MESSAGE] = array('EAP-Message', 'S');
        $this->attributesInfo[RadiusAttributes::MESSAGE_AUTHENTICATOR] = array('Message-Authenticator', 'S');

        $this->identifierToSend = -1;
        $this->chapIdentifier   = 1;

        $this->generateRequestAuthenticator()
             ->setServer($radiusHost)
             ->setSecret($sharedSecret)
             ->setAuthenticationPort($authenticationPort)
             ->setAccountingPort($accountingPort)
             ->setTimeout($timeout)
             ->setRadiusSuffix($radiusSuffix);

        $this->clearError()
             ->clearDataToSend()
             ->clearDataReceived();
    }

    /**
     * Returns a string of the last error message and code, if any.
     *
     * @return string The last error message and code, or an empty string if no error set.
     */
    public function getLastError()
    {
        if (0 < $this->errorCode) {
            return $this->errorMessage.' ('.$this->errorCode.')';
        } 
        
        return '';
    }

    /**
     * Get the code of the last error.
     *
     * @return int  The error code
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * Get the message of the last error.
     *
     * @return string  The last error message
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * Enable or disable debug (console) output.
     *
     * @param bool $enabled  boolean true to enable debugging, anything else to disable it.
     *
     * @return self
     */
    public function setDebug($enabled = true)
    {
        $this->debug = (true === $enabled);
        return $this;
    }

    /**
     * Set the hostname or IP address of the RADIUS server to send requests to.
     *
     * @param string $hostOrIp  The hostname or IP address of the RADIUS server
     * @return self
     */
    public function setServer($hostOrIp)
    {
        $this->server = gethostbyname($hostOrIp);
        return $this;
    }

    /**
     * Set the RADIUS shared secret between the client and RADIUS server.
     *
     * @param string $secret  The shared secret
     * @return self
     */
    public function setSecret($secret)
    {
        $this->secret = $secret;
        return $this;
    }

    /**
     * Gets the currently set RADIUS shared secret.
     *
     * @return string  The shared secret
     */
    public function getSecret()
    {
        return $this->secret;
    }

    /**
     * Set the username suffix for authentication (e.g. '.ppp').
     * This must be set before setting the username.
     *
     * @param string $suffix  The RADIUS user suffix (e.g. .ppp)
     * @return self
     */
    public function setRadiusSuffix($suffix)
    {
        $this->suffix = $suffix;
        return $this;
    }

    /**
     * Set the username to authenticate as with the RADIUS server.
     * If the username does not contain the '@' character, then the RADIUS suffix
     * will be appended to the username.
     *
     * @param string $username  The username for authentication
     * @return self
     */
    public function setUsername($username = '')
    {
        if (false === strpos($username, '@'))
        {
            $username .= $this->suffix;
        }

        $this->username = $username;
        $this->setAttribute(RadiusAttributes::USER_NAME, $this->username);

        return $this;
    }

    /**
     * Get the authentication username for RADIUS requests.
     *
     * @return string  The username for authentication
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Set the User-Password for PAP authentication.
     * Do not use this if you will be using CHAP-MD5, MS-CHAP v1 or MS-CHAP v2 passwords.
     *
     * @param string $password  The plain text password for authentication
     * @return self
     */
    public function setPassword($password)
    {
        $this->password    = $password;
        $encryptedPassword = $this->getEncryptedPassword($password, $this->getSecret(), $this->getRequestAuthenticator());

        $this->setAttribute(RadiusAttributes::USER_PASSWORD, $encryptedPassword);

        return $this;
    }

    /**
     * Get the plaintext password for authentication.
     *
     * @return string  The authentication password
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Get a RADIUS encrypted password from a plaintext password, shared secret, and request authenticator.
     * This method should generally not need to be called directly.
     *
     * @param string $password The plain text password
     * @param string $secret   The RADIUS shared secret
     * @param string $requestAuthenticator  16 byte request authenticator
     * @return string  The encrypted password
     */
    public function getEncryptedPassword($password, $secret, $requestAuthenticator)
    {
        $encryptedPassword = '';
        $paddedPassword    = $password;

        if (0 != (strlen($password) % 16)) {
            $paddedPassword .= str_repeat(chr(0), (16 - strlen($password) % 16));
        }

        $previous = $requestAuthenticator;

        for ($i = 0; $i < (strlen($paddedPassword) / 16); ++$i) {
            $temp = md5($secret . $previous);

            $previous = '';
            for ($j = 0; $j <= 15; ++$j) {
                $value1 = ord($paddedPassword[($i * 16) + $j]);
                $value2 = hexdec(substr($temp, 2 * $j, 2));
                $xor_result = $value1 ^ $value2;
                $previous .= chr($xor_result);
            }
            $encryptedPassword .= $previous;
        }

        return $encryptedPassword;
    }

    /**
     * Set whether a Message-Authenticator attribute (80) should be included in the request.
     * Note: Some servers (e.g. Microsoft NPS) may be configured to require all packets contain this.
     *
     * @param bool $include  Boolean true to include in packets, false otherwise
     * @return self
     */
    public function setIncludeMessageAuthenticator($include = true)
    {
        if ($include) {
            $this->setAttribute(RadiusAttributes::MESSAGE_AUTHENTICATOR, str_repeat("\x00", 16));
        } else {
            $this->removeAttribute(RadiusAttributes::MESSAGE_AUTHENTICATOR);
        }

        return $this;
    }

    /**
     * Sets the next sequence number that will be used when sending packets.
     * There is generally no need to call this method directly.
     *
     * @param int $nextId  The CHAP packet identifier number
     * @return self
     */
    public function setChapId($nextId)
    {
        $this->chapIdentifier = (int)$nextId;

        return $this;
    }

    /**
     * Get the CHAP ID and increment the counter.
     *
     * @return int  The CHAP identifier for the next packet
     */
    public function getChapId()
    {
        $id = $this->chapIdentifier;
        $this->chapIdentifier++;

        return $id;
    }

    /**
     * Set the CHAP password (for CHAP authentication).
     *
     * @param string $password  The plaintext password to hash using CHAP.
     * @return self
     */
    public function setChapPassword($password)
    {
        $chapId = $this->getChapId();
        $chapMd5 = $this->getChapPassword($password, $chapId, $this->getRequestAuthenticator());

        $this->setAttribute(RadiusAttributes::CHAP_PASSWORD, pack('C', $chapId) . $chapMd5);

        return $this;
    }

    /**
     * Generate a CHAP password.  There is generally no need to call this method directly.
     *
     * @param string $password  The password to hash using CHAP
     * @param int    $chapId    The CHAP packet ID
     * @param string $requestAuthenticator  The request authenticator value
     * @return string The hashed CHAP password
     */
    public function getChapPassword($password, $chapId, $requestAuthenticator)
    {
        return md5(pack('C', $chapId) . $password . $requestAuthenticator, true);
    }

    /**
     * Set the MS-CHAP password in the RADIUS packet (for authentication using MS-CHAP passwords)
     *
     * @param string  $password  The plaintext password
     * @param string  $challenge The CHAP challenge
     * @return self
     */
    public function setMsChapPassword($password, $challenge = null)
    {
        $chap = new \Crypt_CHAP_MSv1();
        $chap->chapid   = random_int(1, 255);
        $chap->password = $password;
        if (is_null($challenge)) {
            $chap->generateChallenge();
        } else {
            $chap->challenge = $challenge;
        }

        $response = "\x00\x01" . str_repeat ("\0", 24) . $chap->ntChallengeResponse();

        $this->setIncludeMessageAuthenticator();
        $this->setVendorSpecificAttribute(VendorId::MICROSOFT, 11, $chap->challenge);
        $this->setVendorSpecificAttribute(VendorId::MICROSOFT, 1, $response);

        return $this;
    }

    /**
     * Sets the Network Access Server (NAS) IP address (the RADIUS client IP).
     *
     * @param string $hostOrIp  The hostname or IP address of the RADIUS client
     * @return self
     */
    public function setNasIPAddress($hostOrIp = '')
    {
        if (0 < strlen($hostOrIp)) {
            $this->nasIpAddress = gethostbyname($hostOrIp);
        } else {
            $hostOrIp = @php_uname('n');
            if (empty($hostOrIp)) {
                $hostOrIp = (isset($_SERVER['HTTP_HOST'])) ? $_SERVER['HTTP_HOST'] : '';
            }
            if (empty($hostOrIp)) {
                $hostOrIp = (isset($_SERVER['SERVER_ADDR'])) ? $_SERVER['SERVER_ADDR'] : '0.0.0.0';
            }

            $this->nasIpAddress = gethostbyname($hostOrIp);
        }

        $this->setAttribute(RadiusAttributes::NAS_IP_ADDRESS, $this->nasIpAddress);

        return $this;
    }

    /**
     * Get the currently set NAS IP address
     *
     * @return string  The NAS hostname or IP
     */
    public function getNasIPAddress()
    {
        return $this->nasIpAddress;
    }

    /**
     * Set the physical port number of the NAS which is authenticating the user.
     *
     * @param int $port  The NAS port
     * @return self
     */
    public function setNasPort($port = 0)
    {
        $this->nasPort = (int) $port;
        $this->setAttribute(RadiusAttributes::NAS_PORT, $this->nasPort);

        return $this;
    }

    /**
     * Get the NAS port attribute
     *
     * @return string
     */
    public function getNasPort()
    {
        return $this->nasPort;
    }

    /**
     * Set the timeout (in seconds) after which we'll give up waiting for a response from the RADIUS server.
     *
     * @param int $timeout  The timeout (in seconds) for waiting for RADIUS responses.
     * @return self
     */
    public function setTimeout($timeout = 5)
    {
        if ((int) $timeout > 0) {
            $this->timeout = (int) $timeout;
        }

        return $this;
    }

    /**
     * Get the current timeout value for RADIUS response packets.
     *
     * @return int  The timeout
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * Set the port number used by the RADIUS server for authentication (default = 1812).
     *
     * @param int $port  The port for sending Access-Request packets
     * @return self
     */
    public function setAuthenticationPort($port)
    {
        if (((int) $port > 0) && ((int) $port < 65536)) {
            $this->authenticationPort = (int) $port;
        }

        return $this;
    }

    /**
     * Get the port number used for authentication
     *
     * @return int  The RADIUS auth port
     */
    public function getAuthenticationPort()
    {
        return $this->authenticationPort;
    }

    /**
     * Set the port number used by the RADIUS server for accounting (default = 1813)
     *
     * @param int $port  The port for sending Accounting request packets
     * @return self
     */
    public function setAccountingPort($port)
    {
        if (((int) $port > 0) && ((int) $port < 65536))
        {
            $this->accountingPort = (int) $port;
        }

        return $this;
    }

    /**
     * Returns the raw wire data of the last received RADIUS packet.
     *
     * @return string  The raw packet data of the last RADIUS response
     */
    public function getResponsePacket()
    {
        return $this->radiusPacketReceived;
    }

    /**
     * Alias of Radius::getAttribute()
     *
     * @param int $type  The attribute ID to get
     * @return NULL|string NULL if no such attribute was set in the response packet, or the data of that attribute
     */
    public function getReceivedAttribute($type)
    {
        return $this->getAttribute($type);
    }

    /**
     * Returns an array of all attributes from the last received RADIUS packet.
     *
     * @return array  Array of received attributes.  Each entry is an array with $attr[0] = attribute ID, $attr[1] = data
     */
    public function getReceivedAttributes()
    {
        return $this->attributesReceived;
    }

    /**
     * For debugging purposes.  Print the attributes from the last received packet as a readable string
     *
     * @return string  The RADIUS packet attributes in human readable format
     */
    public function getReadableReceivedAttributes()
    {
        $attributes = '';

        if (isset($this->attributesReceived)) {
            foreach($this->attributesReceived as $receivedAttr) {
                $info = $this->getAttributesInfo($receivedAttr[0]);
                $attributes .= sprintf('%s: ', $info[0]);

                if (26 == $receivedAttr[0]) {
                    $vendorArr = $this->decodeVendorSpecificContent($receivedAttr[1]);
                    foreach($vendorArr as $vendor) {
                        $attributes .= sprintf('Vendor-Id: %s, Vendor-type: %s, Attribute-specific: %s',
                                               $vendor[0], $vendor[1], $vendor[2]);
                    }
                } else {
                    $attributes = $receivedAttr[1];
                }

                $attributes .= "<br>\n";
            }
        }

        return $attributes;
    }

    /**
     * Get the value of an attribute from the last received RADIUS response packet.
     *
     * @param int $type    The attribute ID to get
     * @return NULL|string NULL if no such attribute was set in the response packet, or the data of that attribute
     */
    public function getAttribute($type)
    {
        $value = null;

        if (is_array($this->attributesReceived)) {
            foreach($this->attributesReceived as $attr) {
                if ((int) $type == $attr[0]) {
                    $value = $attr[1];
                    break;
                }
            }
        }

        return $value;
    }

    /**
     * Gets the name of a RADIUS packet from the numeric value.
     * This is only used for debugging functions
     *
     * @param int $info_index  The packet type number
     * @return mixed|string
     */
    public function getRadiusPacketInfo($info_index)
    {
        if (isset($this->radiusPackets[(int) $info_index])) {
            return $this->radiusPackets[(int) $info_index];
        } 
            
        return '';
    }

    /**
     * Gets the info about a RADIUS attribute identifier such as the attribute name and data type.
     * This is used internally for encoding packets and debug output.
     *
     * @param int $info_index  The RADIUS packet attribute number
     * @return array 2 element array with Attribute-Name and Data Type
     */
    public function getAttributesInfo($info_index)
    {
        if (isset($this->attributesInfo[(int) $info_index])) {
            return $this->attributesInfo[(int) $info_index];
        } 

        return array('', '');
    }

    /**
     * Set an arbitrary RADIUS attribute to be sent in the next packet.
     *
     * @param int    $type  The number of the RADIUS attribute
     * @param mixed  $value  The value of the attribute
     * @return self
     */
    public function setAttribute($type, $value)
    {
        $index = -1;
        if (is_array($this->attributesToSend)) {
            foreach($this->attributesToSend as $i => $attr) {
                if (is_array($attr)) {
                    $tmp = $attr[0];
                } else {
                    $tmp = $attr;
                }
                if ($type == ord(substr($tmp, 0, 1))) {
                    $index = $i;
                    break;
                }
            }
        }

        $temp = null;

        if (isset($this->attributesInfo[$type])) {
            switch ($this->attributesInfo[$type][1]) {
                case 'T':
                    // Text, 1-253 octets containing UTF-8 encoded ISO 10646 characters (RFC 2279).
                    $temp = chr($type) . chr(2 + strlen($value)) . $value;
                    break;
                case 'S':
                    // String, 1-253 octets containing binary data (values 0 through 255 decimal, inclusive).
                    $temp = chr($type) . chr(2 + strlen($value)) . $value;
                    break;
                case 'A':
                    // Address, 32 bit value, most significant octet first.
                    $ip = explode('.', $value);
                    $temp = chr($type) . chr(6) . chr($ip[0]) . chr($ip[1]) . chr($ip[2]) . chr($ip[3]);
                    break;
                case 'I':
                    // Integer, 32 bit unsigned value, most significant octet first.
                    $temp = chr($type) . chr(6) .
                            chr((int) ($value / (256 * 256 * 256)) % 256) .
                            chr((int) ($value / (256 * 256)) % 256) .
                            chr((int) ($value / (256)) % 256) .
                            chr($value % 256);
                    break;
                case 'D':
                    // Time, 32 bit unsigned value, most significant octet first -- seconds since 00:00:00 UTC, January 1, 1970. (not used in this RFC)
                    $temp = null;
                    break;
                default:
                    $temp = null;
            }
        }

        $multiAVP = array(26, 79); // vendor specific and EAP-Message
        if ($index > -1) {
            if (in_array($type, $multiAVP)) {
                $this->attributesToSend[$index][] = $temp;
                $action = 'Added';
            } else {
                $this->attributesToSend[$index] = $temp;
                $action = 'Modified';
            }
        } else {
            $this->attributesToSend[] = (in_array($type, $multiAVP)) ? array($temp) : $temp;
            $action = 'Added';
        }

        $info = $this->getAttributesInfo($type);
        $this->debugInfo("{$action} Attribute {$type} ({$info[0]}), format {$info[1]}, value <em>{$value}</em>");

        return $this;
    }

    /**
     * Get one or all set attributes to send
     *
     * @param int|null $type  RADIUS attribute type, or null for all
     * @return mixed array of attributes to send, or null if specific attribute not found, or
     */
    public function getAttributesToSend($type = null)
    {
        if (is_array($this->attributesToSend)) {
            if ($type == null) {
                return $this->attributesToSend;
            } 
            
            foreach($this->attributesToSend as $i => $attr) {
                if (is_array($attr)) {
                    $tmp = $attr[0];
                } else {
                    $tmp = $attr;
                }
                if ($type == ord(substr($tmp, 0, 1))) {
                    return $this->decodeAttribute(substr($tmp, 2), $type);
                }
            }

            return null;
        }

        return array();
    }

    /**
     * Adds a vendor specific attribute to the RADIUS packet
     *
     * @param int    $vendorId  The RADIUS vendor ID
     * @param int    $attributeType  The attribute number of the vendor specific attribute
     * @param mixed  $attributeValue The data for the attribute
     * @return self
     */
    public function setVendorSpecificAttribute($vendorId, $attributeType, $attributeValue)
    {
        $data  = pack('N', $vendorId);
        $data .= chr($attributeType);
        $data .= chr(2 + strlen($attributeValue));
        $data .= $attributeValue;

        $this->setAttribute(RadiusAttributes::VENDOR_SPECIFIC, $data);

        return $this;
    }

    /**
     * Remove an attribute from a RADIUS packet
     *
     * @param int    $type  The attribute number to remove
     * @return self
     */
    public function removeAttribute($type)
    {
        if (is_array($this->attributesToSend)) {
            foreach($this->attributesToSend as $i => $attr) {
                if (is_array($attr)) {
                    $tmp = $attr[0];
                } else {
                    $tmp = $attr;
                }
                if ($type == ord(substr($tmp, 0, 1))) {
                    unset($this->attributesToSend[$i]);
                    break;
                }
            }
        }

        return $this;
    }

    /**
     * Clear all attributes to send so the next packet contains no attributes except ones added after calling this function.
     *
     * @return self
     */
    public function resetAttributes()
    {
        $this->attributesToSend = null;
        return $this;
    }

    /**
     * Remove vendor specific attributes from the request.
     *
     * @return self
     */
    public function resetVendorSpecificAttributes()
    {
        $this->removeAttribute(RadiusAttributes::VENDOR_SPECIFIC);

        return $this;
    }

    /**
     * Decodes a vendor specific attribute in a response packet
     *
     * @param string $rawValue  The raw packet attribute data as seen on the wire
     * @return array  Array of vendor specific attributes in the response packet
     */
    public function decodeVendorSpecificContent($rawValue)
    {
        $result   = array();
        $offset   = 0;
        $vendorId = (ord($rawValue[0]) * 256 * 256 * 256) +
                    (ord($rawValue[1]) * 256 * 256) +
                    (ord($rawValue[2]) * 256) +
                     ord($rawValue[3]);

        $offset += 4;
        $len = strlen($rawValue);

        while ($offset < $len) {
            $vendorType        = (ord($rawValue[$offset]));
            $vendorLength      = (ord($rawValue[1 + $offset]));
            $attributeSpecific = substr($rawValue, 2 + $offset, $vendorLength);
            $result[]          = array($vendorId, $vendorType, $attributeSpecific);
            $offset           += $vendorLength;
        }

        return $result;
    }

    /**
     * Issue an Access-Request packet to the RADIUS server.
     *
     * @param string $username  Username to authenticate as
     * @param string $password  Password to authenticate with using PAP
     * @param int    $timeout   The timeout (in seconds) to wait for a response packet
     * @param string $state     The state of the request (default is Service-Type=1)
     * @return boolean          true if the server sent an Access-Accept packet, false otherwise
     */
    public function accessRequest($username = '', $password = '', $timeout = 0, $state = null)
    {
        $this->clearDataReceived()
             ->clearError()
             ->setPacketType(self::TYPE_ACCESS_REQUEST);

        if (0 < strlen($username)) {
            $this->setUsername($username);
        }

        if (0 < strlen($password)) {
            $this->setPassword($password);
        }

        if ($state !== null) {
            $this->setAttribute(RadiusAttributes::STATE, $state);
        } else {
            $this->setAttribute(RadiusAttributes::SERVICE_TYPE, 1); // 1=Login
        }

        if ((int) $timeout > 0) {
            $this->setTimeout($timeout);
        }

        $packetData = $this->generateRadiusPacket();

        $conn = $this->sendRadiusRequest($packetData);
        if (!$conn) {
            $this->debugInfo(sprintf(
                'Failed to send packet to %s; error: %s',
                $this->server,
                $this->getErrorMessage())
            );

            return false;
        }

        $receivedPacket = $this->readRadiusResponse($conn);
        @fclose($conn);

        if (!$receivedPacket) {
            $this->debugInfo(sprintf(
                'Error receiving response packet from %s; error: %s',
                $this->server,
                $this->getErrorMessage())
            );

            return false;
        }

        if (!$this->parseRadiusResponsePacket($receivedPacket)) {
            $this->debugInfo(sprintf(
                'Bad RADIUS response from %s; error: %s',
                $this->server,
                $this->getErrorMessage())
            );

            return false;
        }

        if ($this->radiusPacketReceived == self::TYPE_ACCESS_REJECT) {
            $this->errorCode    = 3;
            $this->errorMessage = 'Access rejected';
        }

        return (self::TYPE_ACCESS_ACCEPT == ($this->radiusPacketReceived));
    }

    /**
     * Perform an accessRequest against a list of servers.  Each server must
     * share the same RADIUS secret.  This is useful if you have more than one
     * RADIUS server.  This function tries each server until it receives an
     * Access-Accept or Access-Reject response.  That is, it will try more than
     * one server in the event of a timeout or other failure.
     *
     * @see \Seed4Me\RadiusClient\Radius::accessRequest()
     *
     * @param array  $serverList  Array of servers to authenticate against
     * @param string $username    Username to authenticate as
     * @param string $password    Password to authenticate with using PAP
     * @param int    $timeout     The timeout (in seconds) to wait for a response packet
     * @param string $state       The state of the request (default is Service-Type=1)
     *
     * @return boolean true if the server sent an Access-Accept packet, false otherwise
     */
    public function accessRequestList($serverList, $username = '', $password = '', $timeout = 0, $state = null)
    {
        $result = false;

        if (!is_array($serverList)) {
            $this->errorCode    = 127;
            $this->errorMessage = sprintf(
                'server list passed to accessRequestList must be array; %s given', gettype($serverList)
            );

            return false;
        }

        $attributes = $this->getAttributesToSend(); // store base attributes

        foreach($serverList as $server) {
            $this->setServer($server);

            $result = $this->accessRequest($username, $password, $timeout, $state);

            if ($result === true) {
                break; // success
            } 
            
            if ($this->getErrorCode() === self::TYPE_ACCESS_REJECT) {
                break; // access rejected
            } 

            /* timeout or other possible transient error; try next host */
            $this->attributesToSend = $attributes; // reset base attributes
        }

        return $result;
    }

    /**
     * Authenticate using EAP-MS-CHAP v2.  This is a 4-way authentication
     * process that sends an Access-Request, receives an Access-Challenge,
     * responds with an Access-Request, and finally sends an Access-Request with
     * an EAP success packet if the last Access-Challenge was a success.
     *
     * Windows Server NPS: EAP Type: MS-CHAP v2
     *
     * @param string $username  The username to authenticate as
     * @param string $password  The plain text password that will be hashed using MS-CHAPv2
     * @return boolean          true if negotiation resulted in an Access-Accept packet, false otherwise
     */
    public function accessRequestEapMsChapV2($username, $password)
    {
        /*
         * RADIUS EAP MS-CHAP-V2 Process:
         * > RADIUS ACCESS_REQUEST w/ EAP identity packet
         * < ACCESS_CHALLENGE w/ MS-CHAP challenge encapsulated in EAP request
         *   CHAP packet contains auth_challenge value
         *   Calculate encrypted password based on challenge for response
         * > ACCESS_REQUEST w/ MS-CHAP challenge response, peer_challenge &
         *   encrypted password encapsulated in an EAP response packet
         * < ACCESS_CHALLENGE w/ MS-CHAP success or failure in EAP packet.
         * > ACCESS_REQUEST w/ EAP success packet if challenge was accepted
         *
         */

        $attributes = $this->getAttributesToSend();


        // compose and send identity packet as a start of authentication
        $eapPacket = EAPPacket::identity($username);

        $this->clearDataToSend()
             ->clearError()
             ->setPacketType(self::TYPE_ACCESS_REQUEST);

        $this->attributesToSend = $attributes;
        $this->setUsername($username)
             ->removeAttribute(RadiusAttributes::EAP_MESSAGE)
             ->setAttribute(RadiusAttributes::EAP_MESSAGE, $eapPacket)
             ->setIncludeMessageAuthenticator();

        $this->accessRequest();

        if ($this->errorCode) {
            return false;
        }

        if ($this->radiusPacketReceived != self::TYPE_ACCESS_CHALLENGE) {
            $this->errorCode    = 102;
            $this->errorMessage = 'Access-Request did not get Access-Challenge response';
            return false;
        }

        $state = $this->getReceivedAttribute(RadiusAttributes::STATE);
        $eap   = $this->getReceivedAttribute(RadiusAttributes::EAP_MESSAGE);

        if ($eap == null) {
            $this->errorCode    = 102;
            $this->errorMessage = 'EAP packet missing from Radius access challenge packet';
            return false;
        }

        $eap = EAPPacket::fromString($eap);

        // checking what type of EAP-Message we have
        // if it is a PEAP proposal, we start an EAP fallback
        if ($eap->type == EAPPacket::TYPE_PEAP_EAP) { // fallback if PEAP
            $eapId     = $eap->id;

            $eapPacket = EAPPacket::legacyNak(EAPPacket::TYPE_EAP_MS_AUTH, $eapId);

            $this->clearDataToSend()
                 ->setPacketType(self::TYPE_ACCESS_REQUEST);

            $this->attributesToSend = $attributes;
            $this->setUsername($username)
                 ->setAttribute(RadiusAttributes::EAP_MESSAGE, $eapPacket)
                 ->setIncludeMessageAuthenticator();

            if (!$this->accessRequest('', '', 0, $state)) {
                return false;
            }

            $eap = $this->getReceivedAttribute(RadiusAttributes::EAP_MESSAGE);

            if ($eap == null) {
                $this->errorCode    = 102;
                $this->errorMessage = 'EAP packet missing from Radius EAP fallback';
                return false;
            }

            $eap = EAPPacket::fromString($eap);
        } elseif ($eap->type == EAPPacket::TYPE_MD5_CHALLENGE) {
            // EAP type MD5, PPP CHAP protocol w/ MD5
            $this->removeAttribute(RadiusAttributes::EAP_MESSAGE)
                ->setChapPassword($password);

            return $this->accessRequest($username);
        }

        // since we have check that we are not in PEAP method, we should be in EAP
        // so let's check this and return error if not
        if ($eap->type != EAPPacket::TYPE_EAP_MS_AUTH) {
            $this->errorCode    = 102;
            $this->errorMessage = 'EAP type is not EAP_MS_AUTH or MD5_CHALLENGE in access response';
            return false;
        }

        $chapPacket = MsChapV2Packet::fromString($eap->data);

        if (!$chapPacket || $chapPacket->opcode != MsChapV2Packet::OPCODE_CHALLENGE) {
            $this->errorCode    = 102;
            $this->errorMessage = 'MS-CHAP-V2 access response packet missing challenge';
            return false;
        }

        $challenge  = $chapPacket->challenge;
        $chapId     = $chapPacket->msChapId;

        $msChapV2   = new \Crypt_CHAP_MSv2;
        $msChapV2->username      = $username;
        $msChapV2->password      = $password;
        $msChapV2->chapid        = $chapId;
        $msChapV2->authChallenge = $challenge;

        $chapPacket->opcode    = MsChapV2Packet::OPCODE_RESPONSE;
        $chapPacket->response  = $msChapV2->challengeResponse();
        $chapPacket->name      = $username;
        $chapPacket->challenge = $msChapV2->peerChallenge;

        $eapPacket = EAPPacket::mschapv2($chapPacket, $chapId);

        $this->clearDataToSend()
             ->setPacketType(self::TYPE_ACCESS_REQUEST);
        $this->attributesToSend = $attributes;
        $this->setUsername($username)
             ->setAttribute(RadiusAttributes::EAP_MESSAGE, $eapPacket)
             ->setIncludeMessageAuthenticator();

        $this->accessRequest('', '', 0, $state);

        if ($this->errorCode) {
            return false;
        }

        $eap = $this->getReceivedAttribute(RadiusAttributes::EAP_MESSAGE);

        if ($eap == null) {
            $this->errorCode    = 102;
            $this->errorMessage = 'EAP packet missing from MS-CHAP-V2 challenge response';
            return false;
        }

        $eap = EAPPacket::fromString($eap);

        if ($eap->type != EAPPacket::TYPE_EAP_MS_AUTH) {
            $this->errorCode    = 102;
            $this->errorMessage = 'EAP type is not EAP_MS_AUTH in access response';
            return false;
        }

        $chapPacket = MsChapV2Packet::fromString($eap->data);

        if ($chapPacket->opcode != MsChapV2Packet::OPCODE_SUCCESS) {
            $this->errorCode = 3;

            $err = (!empty($chapPacket->response)) ? $chapPacket->response : 'General authentication failure';

            $pattern = '/E=(\d{1,10}).*R=(\d).*C=([0-9A-Fa-f]{32}).*V=(\d{1,10})/';

            if (preg_match($pattern, $chapPacket->response, $err)) {
                switch($err[1]) {
                    case '691':
                        $err = 'Authentication failure, username or password incorrect.';
                        break;

                    case '646':
                        $err = 'Authentication failure, restricted logon hours.';
                        break;

                    case '647':
                        $err = 'Account disabled';
                        break;

                    case '648':
                        $err = 'Password expired';
                        break;

                    case '649':
                        $err = 'No dial in permission';
                        break;

                    case '709':
                        $err = 'Error changing password';
                        break;
                }
            }

            $this->errorMessage = $err;
            return false;
        }

        // got a success response - send success acknowledgement
        $eapPacket = EAPPacket::eapSuccess($chapId + 1);
        $state     = $this->getReceivedAttribute(RadiusAttributes::STATE);

        $this->clearDataToSend()
             ->setPacketType(self::TYPE_ACCESS_REQUEST);
        $this->attributesToSend = $attributes;
        $this->setUsername($username)
             ->setAttribute(RadiusAttributes::EAP_MESSAGE, $eapPacket)
             ->setIncludeMessageAuthenticator();

        return $this->accessRequest('', '', 0, $state);
    }

    /**
     * Allows the peer to change the password on the account specified in the preceding Response packet. The Change-Password
     * packet should be sent only if the authenticator reports ERROR_PASSWD_EXPIRED (E=648) in the Message field of the
     * Failure packet. RFC 2759 - 7. Change-Password Packet
     *
     * @param string $username The account username
     * @param string $password The expired password
     * @param string $newPassword The new password for the account
     * @return bool true if the password was changed, otherwise false and $this->errorCode and $this->errorMessage are set
     */
    public function changePasswordEapMsChapV2($username, $password, $newPassword)
    {
        $this->removeAttribute(RadiusAttributes::EAP_MESSAGE);
        $attributes = $this->getAttributesToSend();

        /*
        $resp may be:
            true in case of valid auth (not expired, not disabled, good pwd...)
            false with chap-opcode=failure and err=648
            false with other cases
        */
        $resp = $this->accessRequestEapMsChapV2($username, $password);

        if ($resp) {
            $this->errorCode = 3;
            $this->errorMessage = 'Password must be expired to be changed';
            return false;
        }

        if ($this->radiusPacketReceived == self::TYPE_ACCESS_REJECT) {
            $this->errorCode    = 3;
            $this->errorMessage = 'Access rejected, invalid account';
            return false;
        } elseif ($this->radiusPacketReceived != self::TYPE_ACCESS_CHALLENGE) {
            $this->errorCode    = 102;
            $this->errorMessage = 'Access-Request did not get Access-Challenge response';
            return false;
        }

        $state = $this->getReceivedAttribute(RadiusAttributes::STATE);
        $eap   = $this->getReceivedAttribute(RadiusAttributes::EAP_MESSAGE);

        if ($eap == null) {
            $this->errorCode    = 102;
            $this->errorMessage = 'EAP packet missing from Radius access challenge packet';
            return false;
        }

        $eap = EAPPacket::fromString($eap);

        if ($eap->type != EAPPacket::TYPE_EAP_MS_AUTH) {
            $this->errorCode    = 102;
            $this->errorMessage = 'EAP type is not EAP_MS_AUTH in access response';
            return false;
        }

        $chapPacket = MsChapV2Packet::fromString($eap->data);

        // chap response opcode should be OPCODE_FAILURE, other cases are exceptions
        if (!$chapPacket || $chapPacket->opcode != MsChapV2Packet::OPCODE_FAILURE) {
            $this->errorCode    = 102;
            $this->errorMessage = 'Invalid reply from auth server';
            return false;
        }

        $err      = (!empty($chapPacket->response)) ? $chapPacket->response : 'General authentication failure';
        $pattern  = '/E=(\d{1,10}).*R=(\d).*C=([0-9A-Fa-f]{32}).*V=(\d{1,10})/';
        $pm       = preg_match($pattern, $chapPacket->response, $err);

        if (!$pm) {
            $this->errorCode    = 102;
            $this->errorMessage = 'Invalid reply from auth server';
            return false;
        }

        if ($err[1] == '648') {
            $challenge = pack("H*", $err[3]);
        } else {
            switch($err[1]) {
                case '691':
                    $err = 'Authentication failure, username or password incorrect.';
                    break;

                case '646':
                    $err = 'Authentication failure, restricted logon hours.';
                    break;

                case '647':
                    $err = 'Account disabled';
                    break;

                case '649':
                    $err = 'No dial in permission';
                    break;

                case '709':
                    $err = 'Error changing password';
                    break;
            }

            $this->errorCode    = 3;
            $this->errorMessage = $err;
            return false;
        }

        $chapId     = $chapPacket->msChapId + 1;

        $msChapV2   = new \Crypt_CHAP_MSv2;
        $msChapV2->username      = $username;
        $msChapV2->password      = $password;
        $msChapV2->chapid        = $chapId;
        $msChapV2->authChallenge = $challenge;

        $chapPacket->opcode        = MsChapV2Packet::OPCODE_CHANGEPASS;
        $chapPacket->msChapId      = $chapId;
        $chapPacket->name          = $username;
        $chapPacket->response      = $msChapV2->challengeResponse();
        $chapPacket->challenge     = $msChapV2->peerChallenge;
        $chapPacket->encryptedPwd  = $msChapV2->newPasswordEncryptedWithOldNtPasswordHash($newPassword, $password);
        $chapPacket->encryptedHash = $msChapV2->oldNtPasswordHashEncryptedWithNewNtPasswordHash($newPassword, $password);

        $eapPacketSplit = str_split(EAPPacket::mschapv2($chapPacket, $chapId), 253);

        $this->clearDataToSend()
             ->setPacketType(self::TYPE_ACCESS_REQUEST);
        $this->attributesToSend = $attributes;
        $this->setUsername($username)
             ->setAttribute(RadiusAttributes::EAP_MESSAGE, $eapPacketSplit[0])
             ->setAttribute(RadiusAttributes::EAP_MESSAGE, $eapPacketSplit[1])
             ->setAttribute(RadiusAttributes::EAP_MESSAGE, $eapPacketSplit[2])
             ->setIncludeMessageAuthenticator();

        $resp = $this->accessRequest('', '', 0, $state);

        if ($this->errorCode) {
            $this->errorMessage = 'Password change rejected; new password may not meet the password policy requirements';
            return false;
        }

        // got a success response - send success acknowledgement
        $eapPacket = EAPPacket::eapSuccess($chapId + 1);

        $this->clearDataToSend()
             ->setPacketType(self::TYPE_ACCESS_REQUEST);
        $this->attributesToSend = $attributes;
        $this->setUsername($username)
             ->setAttribute(RadiusAttributes::EAP_MESSAGE, $eapPacket)
             ->setIncludeMessageAuthenticator();

        // returns true if password changed successfully
        return $this->accessRequest('', '', 0, $state);
    }

    /**
     * Perform a EAP-MS-CHAP v2 4-way authentication against a list of servers.
     * Each server must share the same RADIUS secret.
     *
     * @see \Seed4Me\RadiusClient\Radius::accessRequestEapMsChapV2()
     * @see \Seed4Me\RadiusClient\Radius::accessRequestList()
     *
     * @param array $serverList Array of servers to authenticate against
     * @param string $username  The username to authenticate as
     * @param string $password  The plain text password that will be hashed using MS-CHAPv2
     * @return boolean          true if negotiation resulted in an Access-Accept packet, false otherwise
     */
    public function accessRequestEapMsChapV2List($serverList, $username, $password)
    {
        $result = false;

        if (!is_array($serverList)) {
            $this->errorCode    = 127;
            $this->errorMessage = sprintf(
                'server list passed to accessRequestEapMsChapV2List must be array; %s given', gettype($serverList)
            );

            return false;
        }

        $attributes = $this->getAttributesToSend(); // store base attributes

        foreach($serverList as $server) {
            $this->setServer($server);

            $result = $this->accessRequestEapMsChapV2($username, $password);

            if ($result === true) {
                break; // success
            } 
            
            if ($this->getErrorCode() === self::TYPE_ACCESS_REJECT) {
                break; // access rejected
            } 
            
            /* timeout or other possible transient error; try next host */
            $this->attributesToSend = $attributes; // reset base attributes
        }

        return $result;
    }

    /**
     * Send a RADIUS packet over the wire using UDP.
     *
     * @param string $packetData  The raw, complete, RADIUS packet to send
     * @return boolean|resource   false if the packet failed to send, or a socket resource on success
     */
    private function sendRadiusRequest($packetData)
    {
        $packetLen  = strlen($packetData);

        $conn = @fsockopen('udp://' . $this->server, $this->authenticationPort, $errno, $errstr);
        if (!$conn) {
            $this->errorCode    = $errno;
            $this->errorMessage = $errstr;
            return false;
        }

        $sent = fwrite($conn, $packetData);
        if (!$sent || $packetLen != $sent) {
            $this->errorCode    = 55; // CURLE_SEND_ERROR
            $this->errorMessage = 'Failed to send UDP packet';
            return false;
        }

        if ($this->debug) {
            $this->debugInfo(
                sprintf(
                    '<b>Packet type %d (%s) sent to %s</b>',
                    $this->radiusPacket,
                    $this->getRadiusPacketInfo($this->radiusPacket),
                    $this->server
                )
            );
            foreach($this->attributesToSend as $attrs) {
                if (!is_array($attrs)) {
                    $attrs = array($attrs);
                }

                foreach($attrs as $attr) {
                    $attrInfo = $this->getAttributesInfo(ord(substr($attr, 0, 1)));
                    $this->debugInfo(
                        sprintf(
                            'Attribute %d (%s), length (%d), format %s, value <em>%s</em>',
                            ord(substr($attr, 0, 1)),
                            $attrInfo[0],
                            ord(substr($attr, 1, 1)) - 2,
                            $attrInfo[1],
                            $this->decodeAttribute(substr($attr, 2), ord(substr($attr, 0, 1)))
                        )
                    );
                }
            }
        }

        return $conn;
    }

    /**
     * Wait for a UDP response packet and read using a timeout.
     *
     * @param resource $conn  The connection resource returned by fsockopen
     * @return boolean|string false on failure, or the RADIUS response packet
     */
    private function readRadiusResponse($conn)
    {
        stream_set_blocking($conn, false);
        $read    = array($conn);
        $write   = null;
        $except  = null;

        $receivedPacket = '';
        $packetLen      = null;
        $elapsed        = 0;

        do {
            // Loop until the entire packet is read.  Even with small packets,
            // not all data might get returned in one read on a non-blocking stream.

            $t0      = microtime(true);
            $changed = stream_select($read, $write, $except, $this->timeout);
            $t1      = microtime(true);

            if ($changed > 0) {
                $data = fgets($conn, 1024);
                // Try to read as much data from the stream in one pass until 4
                // bytes are read.  Once we have 4 bytes, we can determine the
                // length of the RADIUS response to know when to stop reading.

                if ($data === false) {
                    // recv could fail due to ICMP destination unreachable
                    $this->errorCode    = 56; // CURLE_RECV_ERROR
                    $this->errorMessage = 'Failure with receiving network data';
                    return false;
                }

                $receivedPacket .= $data;

                if (strlen($receivedPacket) < 4) {
                    // not enough data to get the size
                    // this will probably never happen
                    continue;
                }

                if ($packetLen == null) {
                    // first pass - decode the packet size from response
                    $packetLen = unpack('n', substr($receivedPacket, 2, 2));
                    $packetLen = (int)array_shift($packetLen);

                    if ($packetLen < 4 || $packetLen > 65507) {
                        $this->errorCode = 102;
                        $this->errorMessage = "Bad packet size in RADIUS response.  Got {$packetLen}";
                        return false;
                    }
                }

            } elseif ($changed === false) {
                $this->errorCode    = 2;
                $this->errorMessage = 'stream_select returned false';
                return false;
            } else {
                $this->errorCode    = 28; // CURLE_OPERATION_TIMEDOUT
                $this->errorMessage = 'Timed out while waiting for RADIUS response';
                return false;
            }

            $elapsed += ($t1 - $t0);
        } while ($elapsed < $this->timeout && strlen($receivedPacket) < $packetLen);

        return $receivedPacket;
    }

    /**
     * Parse a response packet and do some basic validation.
     *
     * @param string $packet  The raw RADIUS response packet
     * @return boolean  true if the packet was decoded, false otherwise.
     */
    private function parseRadiusResponsePacket($packet)
    {
        $this->radiusPacketReceived = intval(ord(substr($packet, 0, 1)));

        $this->debugInfo(sprintf(
            '<b>Packet type %d (%s) received</b>',
            $this->radiusPacketReceived,
            $this->getRadiusPacketInfo($this->getResponsePacket())
        ));

        if ($this->radiusPacketReceived > 0) {
            $this->identifierReceived = intval(ord(substr($packet, 1, 1)));
            $packetLenRx = unpack('n', substr($packet, 2, 2));
            $packetLenRx = array_shift($packetLenRx);
            $this->responseAuthenticator = bin2hex(substr($packet, 4, 16));
            if ($packetLenRx > 20) {
                $attrContent = substr($packet, 20);
            } else {
                $attrContent = '';
            }

            $authCheck = md5(
                substr($packet, 0, 4) .
                $this->getRequestAuthenticator() .
                $attrContent .
                $this->getSecret()
            );

            if ($authCheck !== $this->responseAuthenticator) {
                $this->errorCode    = 101;
                $this->errorMessage = 'Response authenticator in received packet did not match expected value';
                return false;
            }

            while (strlen($attrContent) > 2) {
                $attrType     = intval(ord($attrContent[0]));
                $attrLength   = intval(ord($attrContent[1]));
                $attrValueRaw = substr($attrContent, 2, $attrLength - 2);
                $attrContent  = substr($attrContent, $attrLength);
                $attrValue    = $this->decodeAttribute($attrValueRaw, $attrType);

                $attr = $this->getAttributesInfo($attrType);
                if (26 == $attrType) {
                    foreach($this->decodeVendorSpecificContent($attrValue) as $vendor) {
                        $this->debugInfo(
                            sprintf(
                                'Attribute %d (%s), length %d, format %s, Vendor-Id: %d, Vendor-type: %s, Attribute-specific: %s',
                                $attrType, $attr[0], $attrLength - 2,
                                $attr[1], $vendor[0], $vendor[1], $vendor[2]
                            )
                        );
                    }
                } else {
                    $this->debugInfo(
                        sprintf(
                            'Attribute %d (%s), length %d, format %s, value <em>%s</em>',
                            $attrType, $attr[0], $attrLength - 2, $attr[1], $attrValue
                        )
                    );
                }

                // TODO: check message authenticator

                $this->attributesReceived[] = array($attrType, $attrValue);
            }
        } else {
            $this->errorCode    = 100;
            $this->errorMessage = 'Invalid response packet received';
            return false;
        }

        return true;
    }

    /**
     * Generate a RADIUS packet based on the set attributes and properties.
     * Generally, there is no need to call this function.  Use one of the accessRequest* functions.
     *
     * @return string  The RADIUS packet
     */
    public function generateRadiusPacket()
    {
        $hasAuthenticator = false;
        $attrContent = '';
        $offset      = null;

        foreach($this->attributesToSend as $i => $attr) {
            $len = strlen($attrContent);

            if (is_array($attr)) {
                // vendor specific (could have multiple attributes)
                $attrContent .= implode('', $attr);
            } else {
                if (ord($attr[0]) == 80) {
                    // If Message-Authenticator is set, note offset so it can be updated
                    $hasAuthenticator = true;
                    $offset = $len + 2; // current length + type(1) + length(1)
                }

                $attrContent .= $attr;
            }
        }

        $attrLen    = strlen($attrContent);
        $packetLen  = 4; // Radius packet code + Identifier + Length high + Length low
        $packetLen += strlen($this->getRequestAuthenticator()); // Request-Authenticator
        $packetLen += $attrLen; // Attributes

        $packetData  = chr($this->radiusPacket);
        $packetData .= pack('C', $this->getNextIdentifier());
        $packetData .= pack('n', $packetLen);
        $packetData .= $this->getRequestAuthenticator();
        $packetData .= $attrContent;

        if ($hasAuthenticator && !is_null($offset)) {
            $messageAuthenticator = hash_hmac('md5', $packetData, $this->secret, true);
            // calculate packet hmac, replace hex 0's with actual hash
            for ($i = 0; $i < strlen($messageAuthenticator); ++$i) {
                $packetData[20 + $offset + $i] = $messageAuthenticator[$i];
            }
        }

        return $packetData;
    }

    /**
     * Set the RADIUS packet identifier that will be used for the next request
     *
     * @param int    $identifierToSend  The packet identifier to send
     * @return self
     */
    public function setNextIdentifier($identifierToSend = 0)
    {
        $id = (int)$identifierToSend;

        $this->identifierToSend = $id - 1;

        return $this;
    }

    /**
     * Increment the packet identifier and return the number number
     *
     * @return int     The radius packet id
     */
    public function getNextIdentifier()
    {
        $this->identifierToSend = (($this->identifierToSend + 1) % 256);
        return $this->identifierToSend;
    }

    private function generateRequestAuthenticator()
    {
        $this->requestAuthenticator = '';

        for ($c = 0; $c <= 15; ++$c) {
            $this->requestAuthenticator .= chr(random_int(1, 255));
        }

        return $this;
    }

    /**
     * Set the request authenticator for the packet.  This is for testing only.
     * There is no need to ever call this function.
     *
     * @param string $requestAuthenticator  The 16 octet request identifier
     * @return boolean|self false if the authenticator is invalid length, self otherwise
     */
    public function setRequestAuthenticator($requestAuthenticator)
    {
        if (strlen($requestAuthenticator) != 16) {
            return false;
        }

        $this->requestAuthenticator = $requestAuthenticator;

        return $this;
    }

    /**
     * Get the value of the request authenticator used in request packets
     *
     * @return string  16 octet request authenticator
     */
    public function getRequestAuthenticator()
    {
        return $this->requestAuthenticator;
    }

    protected function clearDataToSend()
    {
        $this->radiusPacket     = 0;
        $this->attributesToSend = null;
        return $this;
    }

    protected function clearDataReceived()
    {
        $this->radiusPacketReceived = 0;
        $this->attributesReceived   = null;
        return $this;
    }

    public function setPacketType($type)
    {
        $this->radiusPacket = $type;
        return $this;
    }

    private function clearError()
    {
        $this->errorCode    = 0;
        $this->errorMessage = '';

        return $this;
    }

    protected function debugInfo($message)
    {
        if ($this->debug) {
            $msg = date('Y-m-d H:i:s'). ' DEBUG: ';
            $msg .= $message;
            $msg .= "<br>\n";

            if (php_sapi_name() === 'cli') {
                $msg = strip_tags($msg);
            }

            echo $msg;
            flush();
        }
    }

    private function decodeAttribute($rawValue, $attributeFormat)
    {
        $value = null;

        if (isset($this->attributesInfo[$attributeFormat])) {
            switch ($this->attributesInfo[$attributeFormat][1]) {
                case 'T':
                case 'S':
                    $value = $rawValue;
                    break;

                case 'A':
                    $value = ord(substr($rawValue, 0, 1)) . '.' .
                             ord(substr($rawValue, 1, 1)) . '.' .
                             ord(substr($rawValue, 2, 1)) . '.' .
                             ord(substr($rawValue, 3, 1));
                    break;

                case 'I':
                    $value = (ord(substr($rawValue, 0, 1)) * 256 * 256 * 256) +
                             (ord(substr($rawValue, 1, 1)) * 256 * 256) +
                             (ord(substr($rawValue, 2, 1)) * 256) +
                              ord(substr($rawValue, 3, 1));
                    break;

                case 'D':
                    $value = null;
                    break;

                default:
                    $value = null;
            }
        }

        return $value;
    }
}
