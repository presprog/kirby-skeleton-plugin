import "./index.css"

window.dispatchEvent(
  new CustomEvent("presprog/my-kirby-plugin:loaded", {
    detail: {
      plugin: "presprog/my-kirby-plugin"
    }
  })
)
