import { rmSync } from "node:fs"

for (const file of ["index.css", "index.js"]) {
  rmSync(new URL(`../${file}`, import.meta.url), { force: true })
}
