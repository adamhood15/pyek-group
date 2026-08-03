# pyek-modal — shared lightbox shell

The scrim, white card, header bar and close button used by **park-map** and
**photo-gallery**. Both components used to carry their own byte-for-byte copy of
this chrome plus its own focus trap, scroll lock, instance registry and exit
timers. This is that code, written once.

## Paste order (Oxygen)

Add these **once per page**, before the components that use them:

| File | Where |
|------|-------|
| `pyek-modal.css` | Oxygen → global stylesheet (or a `<style>` Code Block above the components) |
| `pyek-modal.js`  | Oxygen → footer scripts (or a `<script>` Code Block) |

Then each component's own CSS/JS **after** it.

There is no init call and load order between the shared script and the markup
does not matter — every listener is delegated from `document`.

## Markup contract

```html
<button data-modal-open="my-dialog">Open</button>

<dialog class="pyek-modal" id="my-dialog" aria-labelledby="my-dialog-title">
  <div class="pyek-modal__card">
    <div class="pyek-modal__header">
      <span class="pyek-modal__title" id="my-dialog-title">Title</span>
      <div class="pyek-modal__header-end">
        <button class="pyek-modal__close" data-modal-close aria-label="Close" autofocus>…</button>
      </div>
    </div>
    <div class="pyek-modal__body">…</div>
  </div>
</dialog>
```

- `data-modal-open="<id>"` — opens that dialog. Works on any element, anywhere.
- `data-modal-close` — closes the enclosing `<dialog>`.
- A click that starts **and** ends on the scrim (the dialog's own padding)
  closes it. A drag that starts on the content and releases outside does not.
- `pyekModal.open(dialogElement)` — for components that need to set state before
  the dialog appears (photo-gallery does this).

`role="dialog"`, `aria-modal`, the focus trap, focus restoration, Escape and
background inertness all come from `showModal()`. Don't re-declare them.
`autofocus` decides where the keyboard lands on open.

## What is deliberately not here

- **No reparenting to `<body>`.** A modal `<dialog>` renders in the top layer,
  which no ancestor's `overflow`, `transform`, `filter` or `z-index` can clip.
- **No JS animation.** Enter/exit are `@starting-style` +
  `transition-behavior: allow-discrete`. Browsers without support show and hide
  instantly — a downgrade in polish, not a break.
- **No instance registry / `init()` / `destroy()`.** Delegated listeners survive
  AJAX and soft navigation for free.

## Browser baseline

`<dialog>.showModal()` — Chrome 37, Safari 15.4, Firefox 98.
`@starting-style` / `allow-discrete` — Chrome 117, Safari 17.5, Firefox 129.
Below the second line the modal still opens and closes correctly, just without
the fade.
