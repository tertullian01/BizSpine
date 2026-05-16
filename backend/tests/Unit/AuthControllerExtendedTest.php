
        $this->authController->login($request, $response, []);
    }

    public function testRegisterSuccess()
    {
        $request = $this->createMockRequest([
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'display_name' => 'New User'
        ]);
        $response = $this->createMockResponse();

        // Mock user check (not found)
        $this->pdoStatement->method('fetch')->willReturn(false);
        // Mock insert execution
        $this->pdoStatement->method('execute')->willReturn(true);
        // Mock lastInsertId in case controller uses it
        $this->pdo->method('lastInsertId')->willReturn('1');

        // Expect 201 Created (Standard for resource creation)
        $response->expects($this->once())->method('withStatus')->with(201);

        $this->authController->register($request, $response, []);
    }

    public function testRegisterDuplicateEmail()
    {
        $request = $this->createMockRequest([
            'email' => 'existing@example.com',
            'password' => 'password123',
            'display_name' => 'Existing User'
        ]);
        $response = $this->createMockResponse();

        // Mock user check (found existing user)
        $this->pdoStatement->method('fetch')->willReturn(['id' => 1, 'email' => 'existing@example.com']);

        // Expect 409 Conflict (As per README error handling)
        $response->expects($this->once())->method('withStatus')->with(409);

        $this->authController->register($request, $response, []);
    }
}
