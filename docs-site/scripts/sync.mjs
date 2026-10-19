import { cpSync, rmSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

// The pages live in the packages: copied here because VitePress only reads its own folder.
const site = join(dirname(fileURLToPath(import.meta.url)), '..')
const core = join(site, '..', 'packages', 'core')
const starter = join(site, '..', 'starter')

for (const generated of ['docs', 'starter.md']) {
  rmSync(join(site, generated), { recursive: true, force: true })
}

cpSync(join(core, 'docs'), join(site, 'docs'), { recursive: true })
cpSync(join(starter, 'docs'), join(site, 'docs'), { recursive: true })
cpSync(join(site, '..', 'packages', 'rpc-http', 'README.md'), join(site, 'docs', 'rpc-http.md'))
cpSync(join(site, '..', 'packages', 'stream-redis', 'README.md'), join(site, 'docs', 'stream-redis.md'))
cpSync(join(core, 'README.md'), join(site, 'README.md'))
cpSync(join(starter, 'README.md'), join(site, 'starter.md'))
