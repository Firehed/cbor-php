# CBOR-PHP

Tools for working with the [CBOR](https://tools.ietf.org/html/rfc7049) data format in PHP

## Installation

`composer require firehed/cbor`

## Usage

Note: Only decoding is supporing at this time

### Decoding

```php
$decoder = new Firehed\CBOR\Decoder();

$binaryString = "\x18\x64"; // CBOR for int(100)
$data = $decoder->decode($binaryString);

// OR
$byteArray = [24, 100];
$data = $decoder->decodeArrayOfBytes($byteArray);
```

There is currently very limited support for [tagged types](https://tools.ietf.org/html/rfc7049#section-2.4).
When an unsupported tag is encountered, an `OutOfBoundsException` will be thrown.

## Tagged type support
- [ ] 0 DateTime as string
- [ ] 1 DateTime as epoch
- [X] 2 Positive Bignum (returns as string, requires `bcmath`)
- [X] 3 Negative Bignum (same)
- [ ] 4 Decimal fraction
- [ ] 5 Bigfloat
- [ ] 21 base64url string
- [ ] 22 base64 string
- [ ] 23 base16 string
- [ ] 24 CBOR
- [ ] 32 URI
- [ ] 33 base64url
- [ ] 34 base64
- [ ] 35 regexp
- [ ] 36 MIME message
- [ ] 55799 Self-describing CBOR
