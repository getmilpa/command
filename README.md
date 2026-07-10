<p align="center">
  <a href="https://github.com/getmilpa">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/getmilpa/core/main/art/lockup/milpa-lockup-v-color-dark.svg">
      <img src="https://raw.githubusercontent.com/getmilpa/core/main/art/lockup/milpa-lockup-v-color-light.svg" alt="Milpa" width="300">
    </picture>
  </a>
</p>

# Milpa Command

> The **Command-as-atom** core of Milpa: one surface-agnostic `Operation` value object — schema of
> inputs + handler + metadata (`mutating` / `requiresConfirmation` / `scopes` / `outputSchema` /
> `version` / `path` / `surfaces`) — plus two contracts, `CommandProvider` (the discovery seam a
> plugin implements to declare operations) and `SurfaceProjector` (the contract each surface
> projector implements). One operation, N surfaces — CLI, MCP, HTTP, web, TUI.

[![CI](https://github.com/getmilpa/command/actions/workflows/ci.yml/badge.svg)](https://github.com/getmilpa/command/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/milpa/command.svg)](https://packagist.org/packages/milpa/command)
[![PHP](https://img.shields.io/badge/php-%E2%89%A5%208.3-777bb4.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-Apache--2.0-blue.svg)](LICENSE)
[![Docs](https://img.shields.io/badge/docs-API%20reference-blue.svg)](https://getmilpa.github.io/command/)

`milpa/command` carries the Command-as-atom contracts for Milpa — one operation, written once, that
N surfaces materialize from. An `Operation` is a plain `readonly` value object: a name, a
description, a handler, and the metadata a projector needs to decide how — and whether — to expose
it. **No projector, no kernel, no registry** — just the atom and the two seams everything else binds
to. The concrete surface projectors (CLI, MCP, HTTP, …) live host-side, in the skeleton; this
package has zero package dependencies, Milpa or otherwise.

## Install

```bash
composer require milpa/command
```

## Quick example

```php
use Milpa\Command\CommandProvider;
use Milpa\Command\Operation;
use Milpa\Command\SurfaceProjector;

final class PostsProvider implements CommandProvider
{
    public function operations(): array
    {
        return [
            new Operation(
                name: 'create_post',
                description: 'Create a post',
                handler: [PostsHandler::class, 'create'],
                inputSchema: ['type' => 'object', 'properties' => ['title' => ['type' => 'string']]],
                mutating: true,
                requiresConfirmation: true,
                scopes: ['posts:write'],
                path: '/posts',
            ),
        ];
    }
}

final class CliProjector implements SurfaceProjector
{
    public function surface(): string
    {
        return 'cli';
    }

    public function supports(Operation $op): bool
    {
        return $op->supportsSurface($this->surface());
    }
}
```

One `Operation` — `create_post` — and a `CliProjector` turns it into a `coa` command, an MCP
projector registers it as a tool, an HTTP projector synthesizes the `POST /posts` route. Each
projector decides, per operation, whether and how to project it (`supports()` / `supportsSurface()`);
`milpa/command` never runs any of them — that dispatch is the host's job.

## Two contracts, one atom

| Contract | Role |
|-------|------------|
| `CommandProvider` | The discovery seam a plugin implements to declare the operations it contributes — the kernel checks every booted plugin for it and merges `operations()` into the command table. |
| `SurfaceProjector` | The contract every surface projector implements: reports the surface it targets (`cli`, `mcp`, `http`, …) and whether a given `Operation` opts into that surface. |

The concrete projectors — the CLI command factory, the MCP tool registrar, the HTTP route
synthesizer — live host-side, not in this package.

## Requirements

- PHP **≥ 8.3**
- Nothing else — `milpa/command` has no package dependencies, Milpa or otherwise

## Documentation

**Full API reference: [getmilpa.github.io/command](https://getmilpa.github.io/command/)** —
generated straight from the source DocBlocks and dressed with the Milpa design system.

## Contributing

Contributions are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md). Please report security
issues via [SECURITY.md](SECURITY.md), and note that this project follows a
[Code of Conduct](CODE_OF_CONDUCT.md).

## License

[Apache-2.0](LICENSE) © TeamX Agency.

---

Milpa is designed, built, and maintained by **[TeamX Agency](https://teamx.agency/?utm_source=github&utm_medium=readme&utm_campaign=milpa&utm_content=command)**.
