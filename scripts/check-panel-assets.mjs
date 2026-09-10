import { spawnSync } from "node:child_process"

const executable = (command) => {
  return process.platform === "win32" ? `${command}.cmd` : command
}

const run = (command, args, options = {}) => {
  const result = spawnSync(executable(command), args, {
    encoding: "utf8",
    stdio: "inherit",
    ...options
  })

  if (result.status !== 0) {
    process.exit(result.status ?? 1)
  }

  return result
}

run("corepack", ["yarn", "build"])

const status = spawnSync(
  executable("git"),
  ["status", "--porcelain", "--", "index.js", "index.css"],
  { encoding: "utf8" }
)

if (status.status !== 0) {
  process.stderr.write(status.stderr)
  process.exit(status.status ?? 1)
}

if (status.stdout.trim() === "") {
  process.exit(0)
}

process.stdout.write(status.stdout)

run("git", ["diff", "--", "index.js", "index.css"])

process.stderr.write(
  "Run 'yarn build' and commit the compiled Panel assets.\n"
)

process.exit(1)
