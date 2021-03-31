<?php

use UseDigital\Model\Contracts\CastsAttributes;
use UseDigital\Model\Contracts\CastsInboundAttributes;
use UseDigital\Model\GenericModel;
use PHPUnit\Framework\TestCase;

/**
 * @group integration
 */
class ModelCustomCastingTest extends TestCase
{
    public function testBasicCustomCasting()
    {
        $model = new TestGenericModelWithCustomCast;
        $model->reversed = 'taylor';

        $this->assertEquals('taylor', $model->reversed);
        $this->assertEquals('rolyat', $model->getAttributes()['reversed']);
        $this->assertEquals('rolyat', $model->toArray()['reversed']);

        $unserializedModel = unserialize(serialize($model));

        $this->assertEquals('taylor', $unserializedModel->reversed);
        $this->assertEquals('rolyat', $unserializedModel->getAttributes()['reversed']);
        $this->assertEquals('rolyat', $unserializedModel->toArray()['reversed']);

        $model->setRawAttributes([
            'address_line_one' => '110 Kingsbrook St.',
            'address_line_two' => 'My House',
        ]);

        $this->assertEquals('110 Kingsbrook St.', $model->address->lineOne);
        $this->assertEquals('My House', $model->address->lineTwo);

        $this->assertEquals('110 Kingsbrook St.', $model->toArray()['address_line_one']);
        $this->assertEquals('My House', $model->toArray()['address_line_two']);

        $model->address->lineOne = '117 Spencer St.';

        $this->assertFalse(isset($model->toArray()['address']));
        $this->assertEquals('117 Spencer St.', $model->toArray()['address_line_one']);
        $this->assertEquals('My House', $model->toArray()['address_line_two']);

        $this->assertEquals('117 Spencer St.', json_decode($model->toJson(), true)['address_line_one']);
        $this->assertEquals('My House', json_decode($model->toJson(), true)['address_line_two']);

        $model->address = null;

        $this->assertNull($model->toArray()['address_line_one']);
        $this->assertNull($model->toArray()['address_line_two']);

        $model->options = ['foo' => 'bar'];
        $this->assertEquals(['foo' => 'bar'], $model->options);
        $this->assertEquals(['foo' => 'bar'], $model->options);
        $model->options = ['foo' => 'bar'];
        $model->options = ['foo' => 'bar'];
        $this->assertEquals(['foo' => 'bar'], $model->options);
        $this->assertEquals(['foo' => 'bar'], $model->options);

        $this->assertEquals(json_encode(['foo' => 'bar']), $model->getAttributes()['options']);
    }

    public function testOneWayCasting()
    {
        // CastsInboundAttributes is used for casting that is unidirectional... only use case I can think of is one-way hashing...
        $model = new TestGenericModelWithCustomCast;

        $model->password = 'secret';

        $this->assertEquals(hash('sha256', 'secret'), $model->password);
        $this->assertEquals(hash('sha256', 'secret'), $model->getAttributes()['password']);
        $this->assertEquals(hash('sha256', 'secret'), $model->getAttributes()['password']);
        $this->assertEquals(hash('sha256', 'secret'), $model->password);

        $model->password = 'secret2';

        $this->assertEquals(hash('sha256', 'secret2'), $model->password);
        $this->assertEquals(hash('sha256', 'secret2'), $model->getAttributes()['password']);
        $this->assertEquals(hash('sha256', 'secret2'), $model->getAttributes()['password']);
        $this->assertEquals(hash('sha256', 'secret2'), $model->password);
    }

    public function testCastClassResolution()
    {
        $model = new TestGenericModelWithCustomCast;

        $model->other_password = 'secret';

        $this->assertEquals(hash('md5', 'secret'), $model->other_password);

        $model->other_password = 'secret2';

        $this->assertEquals(hash('md5', 'secret2'), $model->other_password);
    }

    public function testSettingRawAttributesClearsTheCastCache()
    {
        $model = new TestGenericModelWithCustomCast;

        $model->setRawAttributes([
            'address_line_one' => '110 Kingsbrook St.',
            'address_line_two' => 'My House',
        ]);

        $this->assertEquals('110 Kingsbrook St.', $model->address->lineOne);

        $model->setRawAttributes([
            'address_line_one' => '117 Spencer St.',
            'address_line_two' => 'My House',
        ]);

        $this->assertEquals('117 Spencer St.', $model->address->lineOne);
    }
}

class TestGenericModelWithCustomCast extends GenericModel
{
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'address' => AddressCaster::class,
        'password' => HashCaster::class,
        'other_password' => HashCaster::class.':md5',
        'reversed' => ReverseCaster::class,
        'options' => JsonCaster::class,
    ];
}

class HashCaster implements CastsInboundAttributes
{
    public function __construct($algorithm = 'sha256')
    {
        $this->algorithm = $algorithm;
    }

    public function set($model, $key, $value, $attributes)
    {
        return [$key => hash($this->algorithm, $value)];
    }
}

class ReverseCaster implements CastsAttributes
{
    public function get($model, $key, $value, $attributes)
    {
        return strrev($value);
    }

    public function set($model, $key, $value, $attributes)
    {
        return [$key => strrev($value)];
    }
}

class AddressCaster implements CastsAttributes
{
    public function get($model, $key, $value, $attributes)
    {
        return new Address($attributes['address_line_one'], $attributes['address_line_two']);
    }

    public function set($model, $key, $value, $attributes)
    {
        return ['address_line_one' => $value->lineOne, 'address_line_two' => $value->lineTwo];
    }
}

class JsonCaster implements CastsAttributes
{
    public function get($model, $key, $value, $attributes)
    {
        return json_decode($value, true);
    }

    public function set($model, $key, $value, $attributes)
    {
        return json_encode($value);
    }
}

class Address
{
    public $lineOne;
    public $lineTwo;

    public function __construct($lineOne, $lineTwo)
    {
        $this->lineOne = $lineOne;
        $this->lineTwo = $lineTwo;
    }
}
