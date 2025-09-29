SELECT  
    contacts.FirstName AS "First Name",
    contacts.LastName AS "Last Name",
    EmailAddresses.EMAddress AS "Email",
    guests.no_email as 'Opt Out',
    PhoneNumbers.PNNumOnly AS "Phone",
    Addresses.Zip,
    YEAR(gst_pass.expires) AS "Pass Year",
    gst_pass.department AS "Department",
    gst_pass.category AS "Category",
    items.descrip AS "Item",
    cast(gst_pass.date_time as date) as 'Date of Purchase'
FROM
    gst_pass
JOIN
    guests ON gst_pass.guest_no = guests.guest_no
JOIN
    contacts ON guests.ContactId = contacts.ContactId
JOIN
    EmailAddresses ON EmailAddresses.EmId = contacts.PrefEmId
JOIN
    PhoneNumbers ON PhoneNumbers.PhId = contacts.PrefPhId
JOIN
    items ON gst_pass.ItemID = items.item_id
JOIN   
    Addresses on Addresses.AddressId = contacts.PrefAdId
WHERE
    gst_pass.expires > '2026-01-01'
    AND EmailAddresses.EMAddress != ''
    AND gst_pass.department LIKE 'CBCSP'
    AND gst_pass.category NOT LIKE 'AYCD'
    AND gst_pass.category NOT LIKE 'USES'
    AND gst_pass.category NOT LIKE 'PARKING'
    AND gst_pass.voided_for = ''
    
ORDER BY EmailAddresses.EMAddress

-- Single Ticket Purchase This Year
WITH RankedTransactions AS (
    SELECT 
        transact.department AS "Department",
        transact.category AS "Category",
        transact.item AS "Item Code",
        contacts.FirstName AS "First Name",
        contacts.LastName AS "Last Name",
        EmailAddresses.EMAddress AS "Email",
        guests.no_email AS "Opt Out",
        PhoneNumbers.PNNumOnly AS "Phone",
        Addresses.Zip,
        YEAR(transact.expires) AS "Ticket Year",
        items.descrip AS "Item Description",
        CAST(transact.date_time AS date) AS "Date of Purchase",
        ROW_NUMBER() OVER (
            PARTITION BY EmailAddresses.EMAddress 
            ORDER BY transact.date_time DESC
        ) AS rn
    FROM transact
    JOIN resrvatn ON transact.reserv_no = resrvatn.reserv_no
    JOIN guests ON resrvatn.guest_no = guests.guest_no
    JOIN contacts ON guests.ContactId = contacts.ContactId
    JOIN EmailAddresses ON EmailAddresses.EmId = contacts.PrefEmId
    LEFT JOIN PhoneNumbers ON PhoneNumbers.PhId = contacts.PrefPhId
    LEFT JOIN items ON transact.ItemID = items.item_id
    LEFT JOIN Addresses ON Addresses.AddressId = contacts.PrefAdId
    WHERE
        transact.date_time > '2025-01-01'
        AND EmailAddresses.EMAddress != ''
        AND EmailAddresses.EMAddress IS NOT NULL
        AND transact.department LIKE 'TTAADM'
        AND transact.category NOT LIKE 'COMPS'
        AND transact.category NOT LIKE 'DYNAMIC'

)
SELECT *
FROM RankedTransactions
WHERE rn = 1
ORDER BY Email;