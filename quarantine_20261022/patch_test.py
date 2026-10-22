import io

p = 'F:/flutter/online_bookstore/online-bookstore-api/tests/Feature/BookstoreApiTest.php'
lines = io.open(p, encoding='utf-8').readlines()
print('READ lines:', len(lines))

new = [
    '    // ---------------------------------------------------------------- Orders\n',
    '    public function test_checkout_creates_order_from_cart(): void\n',
    '    {\n',
    '        $user = $this->createUser();\n',
    "        $book = $this->createBook(['price' => 25.00, 'stock' => 10]);\n",
    "        Cart::create(['user_id' => $user->id, 'book_id' => $book->id, 'quantity' => 2]);\n",
    '\n',
    "        $response = $this->actingAs($user, 'sanctum')->postJson('/api/orders/checkout', \n",
    "            'shipping_address' => [\n",
    "                'street' => '789 Oak Ave',\n",
    "                'city' => 'Metropolis',\n",
    "                'state' => 'NY',\n",
    "                'zip_code' => '10001',\n",
    "                'country' => 'USA',\n",
    "            ],\n",
    "        ])->assertStatus(201)\n",
    "            ->assertJsonPath('success', true)\n",
    "            ->assertJsonPath('data.status', 'pending')\n",
    "            ->assertJsonPath('data.items.0.quantity', 2);\n",
    '\n',
    "        $this->assertEqualsWithDelta(50.0, $response->json('data.total_amount'), 0.01);\n",
    '\n',
    "        $this->assertDatabaseCount('orders', 1);\n",
    "        $this->assertDatabaseCount('order_items', 1);\n",
    "        $this->assertDatabaseCount('carts', 0);\n",
    "        $this->assertEquals(8, $book->fresh()->stock);\n",
    '    }\n',
    '\n',
    '    public function test_checkout_fails_on_empty_cart(): void\n',
    '    {\n',
    '        $user = $this->createUser();\n',
    '\n',
    "        $this->actingAs($user, 'sanctum')\n",
    "            ->postJson('/api/orders/checkout', [])\n",
    "            ->assertStatus(400);\n",
    '    }\n',
    '\n',
]

start = 261
end = 335
lines[start:end] = new

io.open(p, 'w', encoding='utf-8').writelines(lines)
print('WROTE total lines:', len(lines))
