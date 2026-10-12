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

## Declare the intent; the framework derives the rest

The example above says everything twice: `string $title` in the handler AND `['type' => 'string']` in
the schema; a sentence for the human in a docblock AND a `description` for the agent. Only one half of
that is intent — what it mutates, whose authority it spends, which argument the human must NAME. The
other half is mechanics a PHP type already answers.

So declare the first and derive the second:

```php
use Milpa\Command\Declaration\{Because, Confirms, Mutates, Needs, Operation, Target};
use Milpa\Command\Effect\{Externality, Mutation, Reversibility, Subject};

#[Operation(name: 'posts:publish', description: 'Publish a post.')]
#[Mutates(Mutation::Persistent, Externality::None, Reversibility::Guaranteed, subject: Subject::Data)]
#[Needs(scopes: ['posts:write'])]
#[Confirms]
final readonly class PublishPost
{
    public function __construct(
        #[Target] #[Because('which post to publish')] public string $id,
        public Visibility $visibility = Visibility::Public,
    ) {
    }

    public function run(Posts $posts): Receipt
    {
        return $posts->publish($this->id, $this->visibility);
    }
}
```

The constructor IS the input contract — types become the schema, a parameter without a default is
required, a PHP enum brings its own admissible values, `#[Because]` becomes the field's description.
`run()` is the handler, with its collaborators injected and its return type declaring the output.

```php
use Milpa\Command\Declaration\DeclaredOperation;

$operation = DeclaredOperation::from(PublishPost::class, fn (string $type) => $container->get($type));
```

What comes back is the **same `Operation` value object** the hand-written form builds, so every
projector, the consent flow and the agent's catalogue read exactly what they read before. Both styles
live side by side; the attribute wins where it exists.

**Nothing about authority is ever inferred.** A class that declares neither `#[Mutates]` nor
`#[Reads]` is refused by name — silence would ship as «does not mutate» and every consumer would
believe it. So are two `#[Target]`s, an input type no schema describes, and a `run()` whose
collaborators nobody can resolve. Each refusal happens once, at declaration, and says what to declare.

**Who ran it is attributed, never an argument.** A `run()` that declares `?InvocationContext $context`
receives the context the surface attributed the call to — the verified actor behind a passkey or a
signature, the channel, the authorizing decision — per invocation. It is not part of the input schema,
so a caller cannot write it. It must be nullable: a surface that attributes nothing passes `null`, and
the operation decides what that means (an approval, for instance, refuses).

## Two contracts, one atom

| Contract | Role |
|-------|------------|
| `CommandProvider` | The discovery seam a plugin implements to declare the operations it contributes — the kernel checks every booted plugin for it and merges `operations()` into the command table. |
| `SurfaceProjector` | The contract every surface projector implements: reports the surface it targets (`cli`, `mcp`, `http`, …) and whether a given `Operation` opts into that surface. |

The concrete projectors — the CLI command factory, the MCP tool registrar, the HTTP route
synthesizer — live host-side, not in this package.

## An operation can own its doubt

Since 0.5, an operation may declare that its target must be **named by the human** who asked:

```php
new Operation(
    name: 'plugins.disable',
    // …
    namedTarget: 'name',   // the argument whose value must appear in the request
);
```

Declaring is the whole job of this package — the field is a contract, not a behaviour. Whoever
holds the session enforces it (the framework's session gate does: an agent calling
`plugins.disable {name: X}` when the request never named X gets a **formal question** back, not an
execution). It exists because 160 measured runs showed an ambiguous imperative reads as
authorisation at every link of the chain: ask "remove the old plugin" and a sampled candidate dies,
with no fact saying why. With the contract declared, an unnamed target becomes a question instead.
The doubt belongs to the operation that discovers a concrete operation cannot exist yet.

## A standing grant, and the judgment of one call under it

Some acts repeat, and a person should not have to sign each one. The only way to let a seat repeat
an act used to be handing it the scope whole: no ceiling, no condition, nothing to take it back when
the evidence changes. A **standing grant** is narrower than that on every count. A person signs it
once; it names the seats, the operations, the widest effect a call may have, what must have been
**measured on the very content the act touches**, and what would show the grant was wrong to give.

An operation says what content a call acts on, as a digest derived from the bytes:

```php
new Operation(
    name: 'release:publish',
    // …
    actsOn: static fn (array $arguments): ?string => is_file($arguments['archive'])
        ? 'sha256:' . hash_file('sha256', $arguments['archive'])
        : null,
);
```

The operation that measures a condition declares the same thing, and a host joins the two **by
content, never by clock**: a measurement taken on another candidate, or after the act, is not this
one. `StandingJudge::judge()` then asks seven things, in order, and the first that fails is the
verdict:

| # | Check | Refuses when |
|---|-------|--------------|
| 1 | `Named` | the grant does not name this operation, these arguments and this seat — the call is then judged as it was before any grant existed |
| 2 | `Signature` | the grant's signature does not hold *now*, or what the host keeps of the grant does not read whole — and a host that does not say it verified the one and read the other is read as one that did neither |
| 3 | `InForce` | it is suspended, superseded, revoked, or past its own date |
| 4 | `Watched` | the operation watching its falsifier gave no sign of life within its window |
| 5 | `Subject` | the operation cannot say what content this call acts on |
| 6 | `Ceiling` | the effect of this call is wider than the grant's ceiling (`EffectProfile::isNoWiderThan`, the one comparator) |
| 7 | `Conditions` | a condition has no green receipt on this content, from the instrument as it is today — or the last receipt about this content says no |

```php
$verdict = StandingJudge::judge($grant, $standing, $call);

$verdict->named();    // false: not this grant's call
$verdict->passes();   // true: every check held; $verdict->cited are the receipts it ran on
$verdict->failed;     // the StandingCheck that refused, with $verdict->sentence for the reader
```

The judgment is pure: it reads a `StandingGrant`, the facts a host gathered about it
(`GrantStanding`) and the call as facts (`StandingCall`). It never grants, widens or restores —
growing back is a person's new signature, and it happens in the host.

**Nothing in `GrantStanding` says "yes" by default.** Where the grant stands (`state`) has to be
said. The signature is faulted until the host says it verified it (`signatureFault: null`), and so is
the record: `recordFault: null` says every line the host keeps of this grant was read. A host that
forgets any of the three lends nothing.

A house may hold several grants that name the same call. `StandingJudge::among()` says which
answer stands, and **shrinking wins**: a grant that names the call and is suspended refuses it,
whatever another grant would lend — until a person lifts that suspension or gives a grant that
supersedes it by name. Expired, revoked and superseded grants stop nobody. Otherwise one grant that
passes is enough, and when none does, the refusal that got furthest speaks.

Some documents cannot be written at all. `StandingGrant` refuses, at construction: a ceiling with an
unclassified axis, a ceiling above `compensatable`, a ceiling whose **authority** is above
`write_as_user` (an act above it itself gives authority, and no grant lends one), covering any
`standing.*` operation, a grant with no condition, a falsifier nothing watches, and **any key it
does not read** — a line the judge would ignore is a limit nobody keeps.

**A grant built in code is held to what a grant read from its text is held to.** Every text, every
name and the expiry are refused by the constructors for the same reasons the reader refuses them, so
a grant put together by hand reads back from its own spelling.

**The text a person signs is read with `StandingGrant::fromJson()`**, and it is read as it is shown:

- it says each thing **once** — a key said twice in one object is refused, at every depth, however
  it is spelled (a reader of JSON would take the last one and never mention the first);
- a list is written as a list — never as an object with the keys `"0"`, `"1"`;
- it is **at most 256 KB long** (`StandingGrant::LONGEST`): a longer text is refused before a byte of
  it is read — a grant is a page a person reads before signing it;
- **nothing is taken off the ends of a text.** A reader that trims repairs: the document a person
  signed and the one that is kept are then two. An id, the name of an operation, a seat, a sentence
  — each is given as it reads, and one with a space, a line break, a tab or a null byte before or
  after it is refused. So is the name of an operation with a separator at either end
  (`:release:publish:`); how a surface spells the name inside — `release:publish`,
  `release.publish`, `release_publish`, in capitals or not — is still one operation;
- its **ceiling is read as it is written, or refused** — each axis is one of its values, an inverse
  (`rollback_contract`) is the name of one operation under any ceiling, never a sentence, and what
  the lenient reader of a stored profile would drop or change is not taken;
- `expires_at` is one form — the date, a `T`, the time to the second, then `Z` or the offset — whose
  own spelling in UTC is that form too;
- **every text it keeps is one line of plain text, with nothing in it that is not seen** — `undo`,
  the `name` and `statement` of a condition and of the falsifier, each seat, `lifts` and the inverse
  a ceiling names. A grant is read by a person before they sign it and every time a house lists it
  or a judge names it in a refusal; a text that is read otherwise than its bytes could clear the
  line a terminal printed it on, and two names that read the same could be two.

**What one line of plain text keeps:** the letters of every language, their accents, digits,
punctuation and symbols, and the ordinary space (U+0020) inside it. **What it refuses, exactly**
(`Canonical::oneLine()`):

| Refused | Which characters |
|---------|------------------|
| control characters | U+0000–U+001F, U+007F–U+009F — the line break and the tab among them |
| format characters, every one (general category `Cf`) | U+00AD (soft hyphen), U+0600–U+0605, U+061C, U+06DD, U+070F, U+0890–U+0891, U+08E2, U+180E, U+200B–U+200F (zero-width space, non-joiner, joiner, the two direction marks), U+202A–U+202E, U+2060–U+2064, U+2066–U+206F, U+FEFF, U+FFF9–U+FFFB, U+110BD, U+110CD, U+13430–U+1343F, U+1BCA0–U+1BCA3, U+1D173–U+1D17A, U+E0001, U+E0020–U+E007F (the tags) |
| the line and the paragraph separator | U+2028, U+2029 |
| every space that is not U+0020 (`Zs`) | U+00A0, U+1680, U+2000–U+200A, U+202F, U+205F, U+3000 |
| variation selectors, and the combining grapheme joiner | U+180B–U+180D, U+180F, U+FE00–U+FE0F, U+E0100–U+E01EF; U+034F |
| vowels and fillers that print as nothing | U+17B4, U+17B5 (the Khmer inherent vowels); U+115F, U+1160, U+3164, U+FFA0, U+2800 |
| private use (`Co`), unassigned (`Cn`, the noncharacters and the rest of the tag block among them) | by category |
| bytes that are not text | anything that is not UTF-8 — a surrogate among them |
| a space at either end | U+0020 first or last |

What that costs: a no-break space, a soft hyphen, a zero-width joiner or non-joiner and a variation
selector are part of how some languages and some emoji are written, and a text that needs one is not
taken. Which places are assigned, and which characters are format characters, is read from the
Unicode tables of the PCRE the host runs — the lists above are those of Unicode 16; a host with older
tables refuses a letter added since.

**The rule behind the list: no code point Unicode itself says is shown as nothing is taken** — its
property `Default_Ignorable_Code_Point`. The table covers that whole set, and a test asks every code
point there is. Of the 1,112,064, this reader refuses 957,522. What it does not promise is that two
texts that look the same are one: a letter of another alphabet that looks the same is text.

`fromArray()` reads a document already in an array, and cannot see a key said twice: the array holds
the last one only.

A refusal says back what the drafter wrote **as JSON spells it, in plain characters, and at most a
line of it** (`Canonical::shown()`). A document may be drafted by someone other than the person who
reads the refusal; said back raw, a key could clear the line a terminal printed it on. The judge
names a condition, a falsifier and the content the same way — `condition "no-host-names" was never
measured` — so a name ends where its quote ends, and a letter that is not plain ASCII is written as
its escape there. A sentence the **host** hands the judge (why a grant is stopped, why its signature
or its record does not hold) is repeated as it came when it is one line of plain text, up to 480
characters of it, and spelled out the same way when it is anything else (`Canonical::said()`). The
name of an operation is plain by its own pattern and has no length of its own, so a sentence says it
whole when it is at most a line, and the start of it with its length otherwise (`Canonical::short()`).

A grant names calls in two ways, and the difference is deliberate. What it **covers** is matched
openly: the call carries each fixed argument, and the rest is free — the candidate changes on every
call. The operation that **measures** a condition and the one that **watches** the falsifier are
matched closed (`NamedCall::matchesExactly()`): besides the fixed arguments a call may carry only
those listed in `free`, typically the one that says which candidate. An open match fails open
there — a later run with `--lenient` would speak for the condition.

That is for what speaks **for** a condition. A receipt that says **no** counts from any run:

| The last receipt about this content, by this instrument | The condition |
|----------------------------------------------------------|---------------|
| green, from the run the condition describes | holds |
| green, from any other run — one argument more, the fixed one missing or at another value, no argument at all | is not measured by it: the receipt before it goes on speaking |
| **red, from any run of that operation**, whatever its arguments | does not hold, until the content is measured again by the run the condition describes |
| about another content, or by another operation | says nothing of this condition |

An argument is **fixed to a value a person can read, and no other**: text that is visible ASCII —
the characters from `!` to `~`, single spaces inside, none around — a whole number, or true or
false. A fixed value is compared byte by byte with what a caller sends, so a value that reads
`strict` and carries a zero-width space is another run. What that costs: an argument cannot be fixed
to a value with an accent or a letter of another alphabet. A host that lists a grant, or asks a
person to sign one, shows a fixed value through `Canonical::shown()`.

An argument — fixed or free — is named in **letters, digits and underscores, starting with a
letter**, at most 64 of them. A name with a space after it, or with a letter of another alphabet
that looks the same, is a name no operation gives an argument: a grant that fixed it would cover a
call carrying it beside the real one.

```json
"conditions": [{"name": "no-host-names", "statement": "what is published names no machine",
                "by": {"operation": "release:scan", "arguments": {"strict": true}, "free": ["archive"]}}]
```

A grant that supersedes a **suspended** one says which suspension it lifts (`lifts`, beside
`supersedes`): taking a suspended grant out of the way is growing back, and growing back names what
it lifts.

**One spelling, and what it is.** `StandingGrant::digest()` is the digest of `Canonical::json()`:
every map sorted **by the bytes of its keys**, every list left alone. Not every value survives that
spelling — negative zero, a whole float, a float's precision (`serialize_precision`), a map whose
keys are 0, 1, 2…, a structure nested deeper than JSON is read — and a grant carries none of them. A
host that writes a line with it reads the line back before it trusts it.

**What a host owes a line it can no longer read.** There is one reader for a new document and for
one already signed, and a later version of this contract may refuse what an earlier one took. A
host that keeps signed documents, or receipts, and reads them again treats one it can no longer read
as a **fault of its record** — never as a grant that is not there, or a measurement that was never
taken: the first would bring back whatever that grant had replaced or stopped, and the second would
leave the green receipt before a red one speaking. It says so to the judge in
`GrantStanding::$recordFault`, and the judge refuses on it. `ConditionReceipt::fromArray()` answers
`null` for a line that is not a receipt — one whose `by` names no operation among them — and `null`
is that fault, not "nothing was measured".

**What it cannot say: money.** There is no effect axis for it, so no ceiling bounds what a call
spends, and nothing in an operation's declaration says that it spends. "Do not grant over an
operation that spends" is a rule for whoever signs, not something this package can hold.

And one limit of the receipt: it pins the measuring operation by `Operation::instrumentDigest()` —
its declaration and the body of its handler. A handler that delegates changes behaviour without
changing that digest.

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

[Apache-2.0](LICENSE) © Rodrigo Vicente - TeamX Agency.

---

Milpa is designed, built, and maintained by **[Rodrigo Vicente - TeamX Agency](https://teamx.agency/?utm_source=github&utm_medium=readme&utm_campaign=milpa&utm_content=command)**.
