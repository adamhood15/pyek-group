# Calendar ACF Field Structure

## Field Group: Calendar

### Repeater Field: `calendar`

Contains all daily operating information for the park calendar.

---

# Subfields of `calendar`

## `date`
- **Field Type:** Date Picker
- **Return Format:** `Y-m-d`
- **Example:** `2026-05-18`

---

# Group Field: `waterpark`

Contains operating information specific to the waterpark.

## Subfields of `waterpark`

### `status`
- **Field Type:** Radio Button
- **Choices:**
  - `open`
  - `closed`
  - `bonus`
  - `limited`
  - `weather`

---

### `open_time`
- **Field Type:** Time Picker
- **Return Format:** `H:i`
- **Example:** `10:30`

---

### `close_time`
- **Field Type:** Time Picker
- **Return Format:** `H:i`
- **Example:** `18:00`

---

### `open_time_2`
- **Field Type:** Time Picker
- **Return Format:** `H:i`

Used for secondary operating periods or split operating schedules.

---

### `close_time_2`
- **Field Type:** Time Picker
- **Return Format:** `H:i`

Used for secondary operating periods or split operating schedules.

---

### `enable_weather_guarantee`
- **Field Type:** True / False

Controls whether Weather Guarantee messaging or functionality is enabled for the day.

---

# Group Field: `fun_park`

Contains operating information specific to the fun park.

## Subfields of `fun_park`

### `status`
- **Field Type:** Radio Button
- **Choices:**
  - `open`
  - `closed`
  - `bonus`
  - `limited`
  - `weather`

---

### `open_time`
- **Field Type:** Time Picker
- **Return Format:** `H:i`
- **Example:** `10:30`

---

### `close_time`
- **Field Type:** Time Picker
- **Return Format:** `H:i`
- **Example:** `22:00`

---

### `open_time_2`
- **Field Type:** Time Picker
- **Return Format:** `H:i`

Used for secondary operating periods or split operating schedules.

---

### `close_time_2`
- **Field Type:** Time Picker
- **Return Format:** `H:i`

Used for secondary operating periods or split operating schedules.

---

# Additional Calendar Fields

## `notes`
- **Field Type:** Text
- Used for general operational notes, weather notices, special messaging, or schedule clarifications for the selected date.