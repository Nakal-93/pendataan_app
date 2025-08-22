# Contributing to Aplikasi Pendataan OPD

Terima kasih atas minat Anda untuk berkontribusi pada Aplikasi Pendataan OPD! 🎉

## 📋 Cara Berkontribusi

### 🐛 Melaporkan Bug

1. **Cek Issues yang Ada**
   - Pastikan bug belum dilaporkan sebelumnya
   - Cari di [GitHub Issues](../../issues)

2. **Buat Issue Baru**
   - Gunakan template bug report
   - Sertakan informasi:
     - Versi PHP dan MySQL
     - Browser dan versi
     - Langkah-langkah reproduksi
     - Screenshot jika perlu
     - Error message lengkap

### ✨ Mengusulkan Fitur Baru

1. **Diskusi Awal**
   - Buat issue dengan label "enhancement"
   - Jelaskan kebutuhan dan use case
   - Tunggu feedback dari maintainer

2. **Proposal Detail**
   - Buat dokumen proposal
   - Sertakan mockup/wireframe
   - Estimasi effort development

### 🔧 Development Setup

1. **Fork Repository**
   ```bash
   git clone https://github.com/your-username/pendataan-opd.git
   cd pendataan-opd
   ```

2. **Setup Development Environment**
   ```bash
   # Copy environment
   cp .env.example .env.dev
   
   # Start dengan Docker
   docker-compose -f docker-compose.dev.yml up -d
   
   # Atau setup manual
   ./scripts/setup-dev.sh
   ```

3. **Install Development Tools**
   ```bash
   # Install PHP tools
   composer install --dev
   
   # Install testing tools
   ./vendor/bin/phpunit --version
   ```

### 🎯 Coding Standards

#### PHP Standards (PSR-12)

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Application;
use Exception;

/**
 * Application Controller
 * 
 * Handles application CRUD operations
 */
class ApplicationController
{
    private Application $model;

    public function __construct(Application $model)
    {
        $this->model = $model;
    }

    /**
     * Store new application data
     * 
     * @param array $data Application data
     * @return bool Success status
     * @throws Exception When validation fails
     */
    public function store(array $data): bool
    {
        if (!$this->validateInput($data)) {
            throw new Exception('Invalid input data');
        }

        return $this->model->create($data);
    }

    private function validateInput(array $data): bool
    {
        // Validation logic here
        return true;
    }
}
```

#### HTML/CSS Standards

```html
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Title</title>
</head>
<body>
    <main class="container">
        <section class="form-section">
            <h1 class="form-title">Form Title</h1>
            <!-- Content here -->
        </section>
    </main>
</body>
</html>
```

```css
/* Use meaningful class names */
.form-section {
    max-width: 800px;
    margin: 0 auto;
    padding: 2rem;
}

.form-title {
    color: #333;
    font-size: 1.5rem;
    margin-bottom: 1rem;
}

/* Use CSS custom properties */
:root {
    --primary-color: #007bff;
    --secondary-color: #6c757d;
    --success-color: #28a745;
    --danger-color: #dc3545;
}
```

#### JavaScript Standards

```javascript
// Use modern ES6+ syntax
class FormValidator {
    constructor(formElement) {
        this.form = formElement;
        this.errors = new Map();
    }

    validate() {
        this.errors.clear();
        
        const requiredFields = this.form.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            if (!this.validateField(field)) {
                this.errors.set(field.name, 'Field is required');
            }
        });

        return this.errors.size === 0;
    }

    validateField(field) {
        return field.value.trim() !== '';
    }

    showErrors() {
        this.errors.forEach((message, fieldName) => {
            const field = this.form.querySelector(`[name="${fieldName}"]`);
            this.displayError(field, message);
        });
    }

    displayError(field, message) {
        const errorElement = document.createElement('div');
        errorElement.className = 'error-message';
        errorElement.textContent = message;
        field.parentNode.appendChild(errorElement);
    }
}
```

### 🧪 Testing

#### Unit Tests

```php
<?php

use PHPUnit\Framework\TestCase;
use App\Controllers\ApplicationController;
use App\Models\Application;

class ApplicationControllerTest extends TestCase
{
    private ApplicationController $controller;
    private Application $model;

    protected function setUp(): void
    {
        $this->model = $this->createMock(Application::class);
        $this->controller = new ApplicationController($this->model);
    }

    public function testStoreValidData(): void
    {
        $data = [
            'perangkat_daerah' => 'Dinas Komunikasi',
            'nama_aplikasi' => 'SIMPEG',
            'jenis_aplikasi' => 'Daerah',
            'status_aplikasi' => 'Aktif',
            'nama_pengelola' => 'John Doe',
            'nomor_wa' => '081234567890'
        ];

        $this->model->expects($this->once())
                   ->method('create')
                   ->with($data)
                   ->willReturn(true);

        $result = $this->controller->store($data);
        $this->assertTrue($result);
    }

    public function testStoreInvalidData(): void
    {
        $this->expectException(Exception::class);
        $this->controller->store([]);
    }
}
```

#### Integration Tests

```php
<?php

use PHPUnit\Framework\TestCase;

class DatabaseIntegrationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO(
            'mysql:host=localhost;dbname=test_db',
            'test_user',
            'test_password'
        );
    }

    public function testDatabaseConnection(): void
    {
        $stmt = $this->pdo->query('SELECT 1');
        $this->assertNotFalse($stmt);
    }

    public function testInsertApplication(): void
    {
        $sql = "INSERT INTO aplikasi_opd (nama_aplikasi, perangkat_daerah) VALUES (?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute(['Test App', 'Test OPD']);
        
        $this->assertTrue($result);
    }
}
```

### 📝 Commit Guidelines

#### Commit Message Format
```
<type>(<scope>): <subject>

<body>

<footer>
```

#### Types:
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation
- `style`: Code style changes
- `refactor`: Code refactoring
- `test`: Adding tests
- `chore`: Maintenance tasks

#### Examples:
```bash
git commit -m "feat(auth): add password reset functionality"
git commit -m "fix(database): resolve connection timeout issue"
git commit -m "docs(readme): update installation instructions"
```

### 🔄 Pull Request Process

1. **Create Feature Branch**
   ```bash
   git checkout -b feature/amazing-feature
   ```

2. **Make Changes**
   - Follow coding standards
   - Add tests
   - Update documentation

3. **Test Locally**
   ```bash
   # Run tests
   ./vendor/bin/phpunit
   
   # Check code style
   ./vendor/bin/phpcs
   
   # Run security scan
   ./vendor/bin/psalm
   ```

4. **Commit Changes**
   ```bash
   git add .
   git commit -m "feat: add amazing feature"
   ```

5. **Push and Create PR**
   ```bash
   git push origin feature/amazing-feature
   ```

6. **PR Review Process**
   - Automated tests will run
   - Code review by maintainers
   - Address feedback
   - Merge after approval

### 📊 Code Review Checklist

#### Functionality
- [ ] Code works as expected
- [ ] All tests pass
- [ ] No breaking changes
- [ ] Error handling implemented

#### Security
- [ ] Input validation
- [ ] SQL injection prevention
- [ ] XSS protection
- [ ] CSRF protection
- [ ] Authentication checks

#### Performance
- [ ] No N+1 queries
- [ ] Efficient algorithms
- [ ] Proper caching
- [ ] Optimized database queries

#### Code Quality
- [ ] Follows coding standards
- [ ] Meaningful variable names
- [ ] Proper documentation
- [ ] No code duplication
- [ ] SOLID principles applied

### 🎨 UI/UX Guidelines

#### Design Principles
- **Accessibility**: WCAG 2.1 AA compliance
- **Responsive**: Mobile-first approach
- **Performance**: Fast loading times
- **Usability**: Intuitive user interface

#### Component Standards
```html
<!-- Button Component -->
<button class="btn btn--primary" type="submit">
    <span class="btn__text">Submit</span>
    <span class="btn__icon" aria-hidden="true">→</span>
</button>

<!-- Form Component -->
<div class="form-group">
    <label for="nama-aplikasi" class="form-label required">
        Nama Aplikasi
    </label>
    <input 
        type="text" 
        id="nama-aplikasi" 
        name="nama_aplikasi" 
        class="form-input"
        required
        aria-describedby="nama-aplikasi-help"
    >
    <div id="nama-aplikasi-help" class="form-help">
        Masukkan nama lengkap aplikasi
    </div>
</div>
```

### 🚀 Release Process

1. **Version Bumping**
   ```bash
   # Update version in config
   # Update CHANGELOG.md
   git tag -a v2.1.0 -m "Release version 2.1.0"
   ```

2. **Release Notes**
   - List new features
   - Document bug fixes
   - Include breaking changes
   - Migration instructions

3. **Deployment**
   ```bash
   # Auto-deploy via GitHub Actions
   git push origin main --tags
   ```

### 📞 Getting Help

- **Discord**: [Join our Discord server](#)
- **Email**: developer@madiunkab.go.id
- **Documentation**: [Wiki pages](../../wiki)
- **Stack Overflow**: Tag `pendataan-opd`

### 🏆 Recognition

Contributors will be recognized in:
- README.md contributors section
- Release notes
- Annual contributor list
- Special Discord role

Thank you for contributing! 🙏
