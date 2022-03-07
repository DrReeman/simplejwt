<?php
/*
 * SimpleJWT
 *
 * Copyright (C) Kelvin Mo 2022
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above
 *    copyright notice, this list of conditions and the following
 *    disclaimer in the documentation and/or other materials provided
 *    with the distribution.
 *
 * 3. The name of the author may not be used to endorse or promote
 *    products derived from this software without specific prior
 *    written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS
 * OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE
 * GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER
 * IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR
 * OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN
 * IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

namespace SimpleJWT\Util\ASN1;

/**
 * An ASN.1 value.
 * 
 * An ASN.1 value contains the following components:
 * 
 * - the *type*, which in turn is broken down into:
 *     - the class (universal, applicaiton, context-specific, private)
 *     - the tag number
 * - the *value*, which is stored as a native PHP value (see below)
 * 
 * In addition, the type would indicate whether the value should be
 * encoded using the primitive or constructed method under DER.
 * 
 * The following sets out the native PHP value for each ASN.1 type.
 * 
 * - `INTEGER`: `int` or a `GMP` object
 * - `BIT STRING`: `string`
 * - `OCTET STRING`: `string`
 * - `NULL`: N/A
 * - `OBJECT IDENTIFIER`: `string`
 * - `SEQUENCE`: an array of `Value` objects
 */
class Value {
    const UNIVERSAL_CLASS = 0x00;
    const APPLICATION_CLASS = 0x40;
    const CONTEXT_CLASS = 0x80;
    const PRIVATE_CLASS = 0xC0;

    const INTEGER_TYPE = 0x02;
    const BIT_STRING = 0x03;
    const OCTET_STRING = 0x04;
    const NULL_TYPE = 0x05;
    const OID = 0x06;

    const SEQUENCE = 0x10;
    const SET = 0x11;

    /** @var int $tag */
    protected $tag;

    /** @var mixed $value */
    protected $value;

    /** @var array<string, mixed> $additional */
    protected $additional;

    /** @var bool $is_constructed */
    protected $is_constructed;

    /** @var int $class */
    protected $class;

    /**
     * @param int $tag
     * @param mixed $value
     * @param array<string, mixed> $additional
     * @param bool|null $is_constructed
     * @param int $class
     * @throws ASN1Exception
     */
    function __construct(int $tag, mixed $value, array $additional = [], ?bool $is_constructed = null, int $class = self::UNIVERSAL_CLASS) {
        $this->tag = $tag;
        $this->value = $value;
        $this->additional = $additional;

        if ($is_constructed == null) {
            $this->is_constructed = in_array($tag, [self::SEQUENCE, self::SET]);
        }

        if (in_array($class, [self::UNIVERSAL_CLASS, self::APPLICATION_CLASS, self::CONTEXT_CLASS, self::PRIVATE_CLASS])) {
            $this->class = $class;
        } else {
            throw new ASN1Exception('Invalid class value');
        }
    }

    /**
     * Creates a value representing an integer.
     * 
     * If a string is given as the argument for `$value`, it is treated as a binary string
     * and will be converted to a `GMP` object using the `gmp_import()` function.
     * 
     * @param int|string|\GMP $value
     * @return Value
     */
    static public function integer($value): self {
        if (is_string($value)) {
            $value = gmp_import($value);
        }
        return new self(static::INTEGER_TYPE, $value);
    }

    /**
     * Creates a value representing a bit string.
     * 
     * @param string $value
     * @param int|null $length the length of the bit string, in bits
     * @return Value
     */
    static public function bitString(string $value, ?int $length = null): self {
        if ($length == null) {
            $length = strlen($value) * 8;
        } elseif ($length > strlen($value) * 8) {
            throw new ASN1Exception('Specified length of bit string too long');
        } elseif ($length <= (strlen($value) - 1) * 8) {
            throw new ASN1Exception('Specified length of bit string too short');
        }
        return new self(static::BIT_STRING, $value, [ 'bitstring_length' => $length ]);
    }

    /**
     * Creates a value representing an octet string.
     * 
     * @param string $value
     * @return Value
     */
    static public function octetString(string $value): self {
        return new self(static::OCTET_STRING, $value);
    }

    /**
     * Creates a value representing a null.
     * 
     * @return Value
     */
    static public function null(): self {
        return new self(static::NULL_TYPE, '');
    }

    /**
     * Creates a value representing an OID.
     * 
     * @param string $value
     * @return Value
     */
    static public function oid(string $value): self {
        if (!preg_match('/^([0-2])((\.0)|(\.[1-9][0-9]*))*$/', $value)) {
            throw new ASN1Exception('Not an OID');
        }
        return new self(static::OID, $value);
    }

    /**
     * Creates a value representing a sequence.
     * 
     * @param array<Value> $value
     * @return Value
     */
    static public function sequence(array $value): self {
        if (!is_array($value)) {
            throw new ASN1Exception('Not a sequence');
        }
        return new self(static::SEQUENCE, $value);
    }

    /**
     * Returns the tag for the type
     */
    public function getTag(): int {
        return $this->tag;
    }

    /**
     * Returns the PHP-native value
     */
    public function getValue(): mixed {
        return $this->value;
    }

    /**
     * Returns additional data associated with the value
     * 
     * @return array<string, mixed>
     */
    public function getAdditionalData(): array {
        return $this->additional;
    }

    /**
     * Returns whether this type is a constructed type
     */
    public function isConstructed(): bool {
        return $this->is_constructed;
    }

    /**
     * Returns the class of this type
     */
    public function getClass(): int {
        return $this->class;
    }
}

?>
