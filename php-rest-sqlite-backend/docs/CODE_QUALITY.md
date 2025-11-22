# Code Quality Tools

This project uses automated code quality tools to maintain consistent code style and catch potential bugs before they reach production.

## Tools Overview

### PHP_CodeSniffer (PHPCS)
- **Purpose**: Enforces consistent PHP coding standards
- **Standard**: PSR-12 (PHP Standards Recommendation)
- **Checks**: Code style, formatting, naming conventions, spacing

### PHPStan
- **Purpose**: Static analysis tool for finding bugs and type errors
- **Level**: 4 (Moderate analysis depth)
- **Focus**: Type safety, potential runtime errors, code quality issues

## Installation

The tools are already configured as dev dependencies in `composer.json`. Install them with:

```bash
composer install
```

## Usage

### Running Individual Tools

#### Code Style Checking
```bash
# Check code style compliance
composer run cs

# Fix code style issues automatically
composer run cs:fix
```

#### Static Analysis
```bash
# Run static analysis
composer run stan
```

#### Run All Quality Checks
```bash
# Run all quality checks (style + analysis + tests)
composer run qa
```

### Manual Commands

If you prefer to run tools directly:

```bash
# PHP_CodeSniffer
./vendor/bin/phpcs --standard=PSR12 src/ tests/
./vendor/bin/phpcbf --standard=PSR12 src/ tests/

# PHPStan
./vendor/bin/phpstan analyse --configuration=phpstan.neon
```

## Configuration

### PHP_CodeSniffer
- **Standard**: PSR-12 (Modern PHP coding standard)
- **Paths**: `src/` and `tests/` directories
- **Auto-fixable**: Many issues can be fixed automatically with `cs:fix`

### PHPStan
- **Configuration**: `phpstan.neon`
- **Analysis Level**: 4 (Balanced between strictness and usability)
- **Paths**: `src/` and `tests/` directories
- **Exclusions**: Test bootstrap files and example tests

## Common Issues and Solutions

### PHP_CodeSniffer Issues

#### Line Length
```php
// ❌ Too long
$veryLongVariableNameThatExceedsTheMaximumAllowedLineLength = 'value';

// ✅ Break into multiple lines
$veryLongVariableName = 'value';
```

#### Spacing
```php
// ❌ Missing spaces
if($condition){

// ✅ Correct spacing
if ($condition) {

}
```

#### Naming Conventions
```php
// ❌ Wrong case
class userController { }

// ✅ Correct case
class UserController { }
```

### PHPStan Issues

#### Type Declarations
```php
// ❌ Missing type hint
public function process($data) { }

// ✅ Add type hint
public function process(array $data): array { }
```

#### Null Checks
```php
// ❌ Potential null access
$user = $this->findUser($id);
$name = $user->name; // Could be null

// ✅ Add null check
$user = $this->findUser($id);
$name = $user ? $user->name : 'Unknown';
```

#### Unused Variables
```php
// ❌ Unused variable
public function process($data) {
    $unused = 'value';
    return $data;
}

// ✅ Remove or use variable
public function process($data) {
    return $data;
}
```

## Integration with Development Workflow

### Pre-commit Hooks
Consider adding these checks to your Git pre-commit hooks:

```bash
#!/bin/sh
composer run cs
composer run stan
composer test
```

### CI/CD Integration
Add to your CI/CD pipeline:

```yaml
# GitHub Actions example
- name: Run Code Quality Checks
  run: |
    composer run cs
    composer run stan
    composer test
```

### IDE Integration

#### VS Code
- Install "PHP Sniffer" extension
- Configure to use PSR-12 standard
- Install "PHPStan" extension for real-time analysis

#### PHPStorm
- Built-in PHP_CodeSniffer support
- Configure PHPStan as external tool
- Enable real-time inspections

## Benefits

### Code Consistency
- Uniform coding style across the team
- Easier code reviews
- Better maintainability

### Bug Prevention
- Catch type errors before runtime
- Identify potential null pointer exceptions
- Detect unused code and variables

### Team Productivity
- Automated code review feedback
- Consistent code formatting
- Early error detection

## Best Practices

### Regular Usage
- Run quality checks before committing code
- Fix issues immediately rather than accumulating technical debt
- Review and understand error messages

### Configuration Updates
- Update tool configurations as coding standards evolve
- Add new ignore patterns for legitimate framework-specific code
- Adjust analysis levels based on team maturity

### Team Adoption
- Include quality checks in code review checklists
- Provide training on common issues and solutions
- Celebrate improvements in code quality metrics

## Troubleshooting

### False Positives
Some PHPStan warnings might be false positives. Add them to the `ignoreErrors` section in `phpstan.neon`:

```neon
ignoreErrors:
    - '#Specific error message pattern#'
```

### Performance Issues
If analysis is slow, consider:
- Reducing analysis level temporarily
- Adding more exclude paths
- Running analysis on specific directories

### Tool Conflicts
If tools conflict with your coding style:
- Discuss with team about standards
- Update configurations to match team preferences
- Consider custom rulesets for specific needs

## Resources

- [PHP_CodeSniffer Documentation](https://github.com/squizlabs/PHP_CodeSniffer)
- [PHPStan Documentation](https://phpstan.org/)
- [PSR-12 Coding Standard](https://www.php-fig.org/psr/psr-12/)
- [PHP-FIG Standards](https://www.php-fig.org/)

## Support

For questions about code quality tools or configuration:
1. Check the tool documentation
2. Review existing configuration files
3. Ask team members familiar with the setup
4. Consider updating configurations for specific needs